<?php
// Start output buffering at the beginning
ob_start();

// Initialize session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

require_once '../../backend/config.php';

// Get current month and year
$currentMonth = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$currentYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

$monthNames = [
    1 => 'January',
    2 => 'February',
    3 => 'March',
    4 => 'April',
    5 => 'May',
    6 => 'June',
    7 => 'July',
    8 => 'August',
    9 => 'September',
    10 => 'October',
    11 => 'November',
    12 => 'December'
];

// Get all events for the year
$yearStart = date('Y-01-01', strtotime("$currentYear-01-01"));
$yearEnd = date('Y-12-31', strtotime("$currentYear-12-31"));
$yearEvents = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM academic_calendar WHERE event_date BETWEEN ? AND ? ORDER BY event_date ASC");
    $stmt->execute([$yearStart, $yearEnd]);
    $yearEvents = $stmt->fetchAll();
} catch (PDOException $e) {
    $yearEvents = [];
}

// Organize year events by month
$yearEventsByMonth = [];
foreach ($yearEvents as $event) {
    $month = date('n', strtotime($event['event_date']));
    if (!isset($yearEventsByMonth[$month])) {
        $yearEventsByMonth[$month] = [];
    }
    $yearEventsByMonth[$month][] = $event;
}

$viewType = isset($_GET['view']) ? $_GET['view'] : 'month';
$printBulk = isset($_GET['bulk']) && $_GET['bulk'] == 'all';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Academic Calendar | Smart Choice Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: white;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 12px;
        }

        /* Print Styles */
        @media print {
            body {
                background: white;
                margin: 0;
                padding: 0;
            }

            .no-print {
                display: none !important;
            }

            .page-break {
                page-break-after: always;
                break-after: page;
            }

            .calendar-table {
                break-inside: avoid;
                page-break-inside: avoid;
            }

            .month-container {
                page-break-after: always;
                break-after: page;
                margin-bottom: 20px;
            }

            .month-container:last-child {
                page-break-after: auto;
                break-after: auto;
            }

            @page {
                size: A4 landscape;
                margin: 1cm;
            }

            /* Ensure colors print properly */
            .event-exam,
            .event-holiday,
            .event-meeting,
            .event-general {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .calendar-table th {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .month-title {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }

        /* Print Header */
        .print-header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #1a3a5c;
            padding-bottom: 10px;
        }

        .print-header h1 {
            color: #1a3a5c;
            font-size: 22px;
            margin-bottom: 5px;
        }

        .print-header h3 {
            color: #4a6fa5;
            font-size: 16px;
            margin-bottom: 5px;
        }

        .print-header p {
            color: #6c757d;
            font-size: 11px;
        }

        /* Calendar Table Styles */
        .calendar-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 11px;
        }

        .calendar-table th {
            background: #1a3a5c;
            color: white;
            padding: 8px;
            text-align: center;
            border: 1px solid #2c5a8c;
            font-weight: 600;
        }

        .calendar-table td {
            border: 1px solid #dee2e6;
            vertical-align: top;
            padding: 6px;
            width: 14.28%;
            height: 85px;
        }

        .calendar-table td.other-month {
            background: #f5f5f5;
            color: #adb5bd;
        }

        .calendar-table td.today {
            background: #fff9e6;
            border: 2px solid #f4a261;
        }

        .day-number {
            font-weight: bold;
            font-size: 13px;
            margin-bottom: 5px;
            display: inline-block;
        }

        /* Color-coded event styles */
        .event-item {
            font-size: 8px;
            padding: 2px 4px;
            margin: 2px 0;
            border-radius: 3px;
            white-space: normal;
            word-wrap: break-word;
            line-height: 1.2;
            cursor: pointer;
        }

        .event-exam {
            background: #dc3545;
            color: white;
            border-left: 3px solid #8b0000;
        }

        .event-holiday {
            background: #28a745;
            color: white;
            border-left: 3px solid #006400;
        }

        .event-meeting {
            background: #fd7e14;
            color: white;
            border-left: 3px solid #b45f06;
        }

        .event-general {
            background: #4a6fa5;
            color: white;
            border-left: 3px solid #1a3a5c;
        }

        /* Month Container */
        .month-container {
            margin-bottom: 30px;
        }

        .month-title {
            background: linear-gradient(135deg, #1a3a5c 0%, #0d2b46 100%);
            color: white;
            padding: 10px;
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 10px;
            border-radius: 5px;
        }

        /* Events List Table */
        .events-list {
            margin-top: 20px;
            border-top: 1px solid #dee2e6;
            padding-top: 15px;
        }

        .events-list h5 {
            font-size: 13px;
            margin-bottom: 10px;
            color: #1a3a5c;
            font-weight: bold;
        }

        .events-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }

        .events-table th,
        .events-table td {
            border: 1px solid #dee2e6;
            padding: 6px;
            text-align: left;
        }

        .events-table th {
            background: #e9ecef;
            font-weight: 600;
        }

        /* Color-coded table rows */
        .event-row-exam {
            background: #fff0f0;
            border-left: 4px solid #dc3545;
        }

        .event-row-holiday {
            background: #f0fff0;
            border-left: 4px solid #28a745;
        }

        .event-row-meeting {
            background: #fff5f0;
            border-left: 4px solid #fd7e14;
        }

        .event-row-general {
            background: #f0f5ff;
            border-left: 4px solid #4a6fa5;
        }

        /* Controls */
        .print-controls {
            background: white;
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
            margin-bottom: 20px;
            position: sticky;
            top: 0;
            z-index: 1000;
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        footer {
            text-align: center;
            font-size: 9px;
            color: #6c757d;
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #dee2e6;
        }

        .legend {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-top: 15px;
            padding: 12px 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 11px;
        }

        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 4px;
        }

        .legend-color-exam {
            background: #dc3545;
            border-left: 3px solid #8b0000;
        }

        .legend-color-holiday {
            background: #28a745;
            border-left: 3px solid #006400;
        }

        .legend-color-meeting {
            background: #fd7e14;
            border-left: 3px solid #b45f06;
        }

        .legend-color-general {
            background: #4a6fa5;
            border-left: 3px solid #1a3a5c;
        }

        .badge-count {
            display: inline-block;
            background: #1a3a5c;
            color: white;
            border-radius: 10px;
            padding: 2px 6px;
            font-size: 9px;
            margin-left: 5px;
        }
    </style>
</head>

<body>
    <!-- Print Controls -->
    <div class="print-controls no-print">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-4">
                    <h5 class="mb-0"><i class="bi bi-calendar3"></i> Academic Calendar Print View</h5>
                </div>
                <div class="col-md-8 text-end">
                    <div class="btn-group flex-wrap">
                        <a href="?view=month&month=<?= date('n') ?>&year=<?= $currentYear ?>" class="btn btn-outline-primary <?= $viewType == 'month' && !$printBulk ? 'active' : '' ?>">Current Month</a>
                        <a href="?view=bulk&year=<?= $currentYear ?>&bulk=all" class="btn btn-outline-primary <?= $printBulk ? 'active' : '' ?>">Full Year (All Months)</a>
                        <a href="?view=year&year=<?= $currentYear ?>" class="btn btn-outline-primary <?= $viewType == 'year' ? 'active' : '' ?>">Year at a Glance</a>
                        <button onclick="window.print();" class="btn btn-primary ms-2">
                            <i class="bi bi-printer"></i> Print / Save PDF
                        </button>
                        <a href="academic_calendar.php" class="btn btn-secondary ms-2">Back to Calendar</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid" style="padding: 15px;">

        <!-- BULK MONTHS VIEW (All 12 Months) -->
        <?php if ($printBulk): ?>
            <div class="print-header">
                <h1>SMART CHOICE ACADEMY</h1>
                <h3>Academic Calendar - Full Year <?= $currentYear ?></h3>
                <p>Generated on: <?= date('F d, Y') ?> | All times are subject to change</p>
            </div>

            <?php
            // Loop through all 12 months
            for ($month = 1; $month <= 12; $month++):
                // Get month data
                $monthStartDate = date('Y-m-01', strtotime("$currentYear-$month-01"));

                // Get first day of month (0 = Sunday)
                $firstDayOfMonth = date('w', strtotime($monthStartDate));
                // Adjust to Monday as first day (0 = Monday, 6 = Sunday)
                $firstDayOfMonth = ($firstDayOfMonth == 0) ? 6 : $firstDayOfMonth - 1;

                $totalDays = date('t', strtotime($monthStartDate));

                // Fetch events for this month
                $monthEvents = $yearEventsByMonth[$month] ?? [];

                // Organize events by date
                $monthEventsByDate = [];
                foreach ($monthEvents as $event) {
                    $date = $event['event_date'];
                    if (!isset($monthEventsByDate[$date])) {
                        $monthEventsByDate[$date] = [];
                    }
                    $monthEventsByDate[$date][] = $event;
                }

                // Build calendar grid
                $calendar = [];
                $row = 0;

                // Fill first row with empty cells
                for ($i = 0; $i < $firstDayOfMonth; $i++) {
                    $calendar[$row][$i] = null;
                }

                // Fill days
                for ($day = 1; $day <= $totalDays; $day++) {
                    $col = ($firstDayOfMonth + $day - 1) % 7;
                    if ($col == 0 && $day > 1) {
                        $row++;
                    }
                    $calendar[$row][$col] = $day;
                }

                // Count events for this month
                $examCount = 0;
                $holidayCount = 0;
                $meetingCount = 0;
                foreach ($monthEvents as $event) {
                    if (stripos($event['event_title'], 'exam') !== false) $examCount++;
                    elseif (stripos($event['event_title'], 'holiday') !== false) $holidayCount++;
                    elseif (stripos($event['event_title'], 'meeting') !== false) $meetingCount++;
                }
            ?>
                <div class="month-container">
                    <div class="month-title">
                        <?= $monthNames[$month] ?> <?= $currentYear ?>
                        <?php if ($examCount > 0): ?>
                            <span class="badge-count">📚 Exams: <?= $examCount ?></span>
                        <?php endif; ?>
                        <?php if ($holidayCount > 0): ?>
                            <span class="badge-count">🎉 Holidays: <?= $holidayCount ?></span>
                        <?php endif; ?>
                        <?php if ($meetingCount > 0): ?>
                            <span class="badge-count">📋 Meetings: <?= $meetingCount ?></span>
                        <?php endif; ?>
                    </div>

                    <table class="calendar-table">
                        <thead>
                            <tr>
                                <th>Monday</th>
                                <th>Tuesday</th>
                                <th>Wednesday</th>
                                <th>Thursday</th>
                                <th>Friday</th>
                                <th>Saturday</th>
                                <th>Sunday</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php for ($r = 0; $r <= $row; $r++): ?>
                                <tr>
                                    <?php for ($c = 0; $c < 7; $c++):
                                        $dayNum = $calendar[$r][$c] ?? null;
                                        if ($dayNum) {
                                            $currentDate = sprintf("%04d-%02d-%02d", $currentYear, $month, $dayNum);
                                            $dayEvents = $monthEventsByDate[$currentDate] ?? [];
                                        }
                                    ?>
                                        <td class="<?= $dayNum ? '' : 'other-month' ?>">
                                            <?php if ($dayNum): ?>
                                                <div class="day-number"><?= $dayNum ?></div>
                                                <?php foreach ($dayEvents as $event):
                                                    $eventClass = '';
                                                    $eventIcon = '📌';
                                                    if (stripos($event['event_title'], 'exam') !== false) {
                                                        $eventClass = 'event-exam';
                                                        $eventIcon = '📝';
                                                    } elseif (stripos($event['event_title'], 'holiday') !== false) {
                                                        $eventClass = 'event-holiday';
                                                        $eventIcon = '🎉';
                                                    } elseif (stripos($event['event_title'], 'meeting') !== false) {
                                                        $eventClass = 'event-meeting';
                                                        $eventIcon = '📋';
                                                    } else {
                                                        $eventClass = 'event-general';
                                                        $eventIcon = '📌';
                                                    }

                                                    $shortTitle = strlen($event['event_title']) > 22 ? substr($event['event_title'], 0, 20) . '...' : $event['event_title'];
                                                ?>
                                                    <div class="event-item <?= $eventClass ?>" title="<?= htmlspecialchars($event['event_title']) ?>">
                                                        <?= $eventIcon ?> <?= htmlspecialchars($shortTitle) ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </td>
                                    <?php endfor; ?>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>

                    <!-- Events list for this month with color coding -->
                    <?php if (!empty($monthEvents)): ?>
                        <div class="events-list">
                            <h5>Events - <?= $monthNames[$month] ?> <?= $currentYear ?></h5>
                            <table class="events-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Event Title</th>
                                        <th>Type</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($monthEvents as $event):
                                        $eventType = 'General';
                                        $rowClass = 'event-row-general';
                                        if (stripos($event['event_title'], 'exam') !== false) {
                                            $eventType = 'Examination';
                                            $rowClass = 'event-row-exam';
                                        } elseif (stripos($event['event_title'], 'holiday') !== false) {
                                            $eventType = 'Holiday';
                                            $rowClass = 'event-row-holiday';
                                        } elseif (stripos($event['event_title'], 'meeting') !== false) {
                                            $eventType = 'Meeting';
                                            $rowClass = 'event-row-meeting';
                                        }
                                    ?>
                                        <tr class="<?= $rowClass ?>">
                                            <td><?= date('d M, Y', strtotime($event['event_date'])) ?></td>
                                            <td><strong><?= htmlspecialchars($event['event_title']) ?></strong></td>
                                            <td><?= $eventType ?></td>
                                            <td><?= htmlspecialchars($event['description'] ?? '-') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>

            <!-- Legend -->
            <div class="legend">
                <div class="legend-item">
                    <div class="legend-color legend-color-exam"></div>
                    <span><strong>Examination</strong> - Red</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color legend-color-holiday"></div>
                    <span><strong>Holiday</strong> - Green</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color legend-color-meeting"></div>
                    <span><strong>Meeting</strong> - Orange</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color legend-color-general"></div>
                    <span><strong>General Event</strong> - Blue</span>
                </div>
            </div>

            <!-- SINGLE MONTH VIEW -->
        <?php elseif ($viewType == 'month'):
            $targetMonth = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
            $targetYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
            $monthStartDate = date('Y-m-01', strtotime("$targetYear-$targetMonth-01"));

            $firstDayOfMonth = date('w', strtotime($monthStartDate));
            $firstDayOfMonth = ($firstDayOfMonth == 0) ? 6 : $firstDayOfMonth - 1;
            $totalDays = date('t', strtotime($monthStartDate));

            // Fetch events for this month
            $monthEvents = $yearEventsByMonth[$targetMonth] ?? [];
            $monthEventsByDate = [];
            foreach ($monthEvents as $event) {
                $date = $event['event_date'];
                if (!isset($monthEventsByDate[$date])) {
                    $monthEventsByDate[$date] = [];
                }
                $monthEventsByDate[$date][] = $event;
            }

            // Build calendar
            $calendar = [];
            $row = 0;
            for ($i = 0; $i < $firstDayOfMonth; $i++) {
                $calendar[$row][$i] = null;
            }
            for ($day = 1; $day <= $totalDays; $day++) {
                $col = ($firstDayOfMonth + $day - 1) % 7;
                if ($col == 0 && $day > 1) $row++;
                $calendar[$row][$col] = $day;
            }
        ?>
            <div class="print-header">
                <h1>SMART CHOICE ACADEMY</h1>
                <h3>Academic Calendar - <?= $monthNames[$targetMonth] ?> <?= $targetYear ?></h3>
                <p>Generated on: <?= date('F d, Y') ?></p>
            </div>

            <div class="month-title"><?= $monthNames[$targetMonth] ?> <?= $targetYear ?></div>
            <table class="calendar-table">
                <thead>
                    <tr>
                        <th>Monday</th>
                        <th>Tuesday</th>
                        <th>Wednesday</th>
                        <th>Thursday</th>
                        <th>Friday</th>
                        <th>Saturday</th>
                        <th>Sunday</th>
                    </tr>
                </thead>
                <tbody>
                    <?php for ($r = 0; $r <= $row; $r++): ?>
                        <tr>
                            <?php for ($c = 0; $c < 7; $c++):
                                $dayNum = $calendar[$r][$c] ?? null;
                                if ($dayNum) {
                                    $currentDate = sprintf("%04d-%02d-%02d", $targetYear, $targetMonth, $dayNum);
                                    $dayEvents = $monthEventsByDate[$currentDate] ?? [];
                                }
                            ?>
                                <td class="<?= $dayNum ? '' : 'other-month' ?>">
                                    <?php if ($dayNum): ?>
                                        <div class="day-number"><?= $dayNum ?></div>
                                        <?php foreach ($dayEvents as $event):
                                            $eventClass = '';
                                            $eventIcon = '📌';
                                            if (stripos($event['event_title'], 'exam') !== false) {
                                                $eventClass = 'event-exam';
                                                $eventIcon = '📝';
                                            } elseif (stripos($event['event_title'], 'holiday') !== false) {
                                                $eventClass = 'event-holiday';
                                                $eventIcon = '🎉';
                                            } elseif (stripos($event['event_title'], 'meeting') !== false) {
                                                $eventClass = 'event-meeting';
                                                $eventIcon = '📋';
                                            } else {
                                                $eventClass = 'event-general';
                                            }
                                        ?>
                                            <div class="event-item <?= $eventClass ?>"><?= $eventIcon ?> <?= htmlspecialchars(substr($event['event_title'], 0, 20)) ?></div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
                            <?php endfor; ?>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>

            <?php if (!empty($monthEvents)): ?>
                <div class="events-list">
                    <h5>Event List - <?= $monthNames[$targetMonth] ?> <?= $targetYear ?></h5>
                    <table class="events-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Event Title</th>
                                <th>Type</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($monthEvents as $event):
                                $eventType = 'General';
                                $rowClass = 'event-row-general';
                                if (stripos($event['event_title'], 'exam') !== false) {
                                    $eventType = 'Examination';
                                    $rowClass = 'event-row-exam';
                                } elseif (stripos($event['event_title'], 'holiday') !== false) {
                                    $eventType = 'Holiday';
                                    $rowClass = 'event-row-holiday';
                                } elseif (stripos($event['event_title'], 'meeting') !== false) {
                                    $eventType = 'Meeting';
                                    $rowClass = 'event-row-meeting';
                                }
                            ?>
                                <tr class="<?= $rowClass ?>">
                                    <td><?= date('d M, Y', strtotime($event['event_date'])) ?></td>
                                    <td><strong><?= htmlspecialchars($event['event_title']) ?></strong></td>
                                    <td><?= $eventType ?></td>
                                    <td><?= htmlspecialchars($event['description'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <div class="legend">
                <div class="legend-item">
                    <div class="legend-color legend-color-exam"></div><span>Examination - Red</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color legend-color-holiday"></div><span>Holiday - Green</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color legend-color-meeting"></div><span>Meeting - Orange</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color legend-color-general"></div><span>General Event - Blue</span>
                </div>
            </div>

            <!-- YEAR AT A GLANCE VIEW -->
        <?php else: ?>
            <div class="print-header">
                <h1>SMART CHOICE ACADEMY</h1>
                <h3>Academic Calendar - Year at a Glance <?= $currentYear ?></h3>
                <p>Generated on: <?= date('F d, Y') ?></p>
            </div>

            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px;">
                <?php for ($month = 1; $month <= 12; $month++):
                    $monthStartDate = date('Y-m-01', strtotime("$currentYear-$month-01"));
                    $firstDayOfMonth = date('w', strtotime($monthStartDate));
                    $firstDayOfMonth = ($firstDayOfMonth == 0) ? 6 : $firstDayOfMonth - 1;
                    $totalDays = date('t', strtotime($monthStartDate));
                    $monthEventsList = $yearEventsByMonth[$month] ?? [];

                    // Create event markers with colors
                    $eventDays = [];
                    $eventColors = [];
                    foreach ($monthEventsList as $event) {
                        $dayNum = (int)date('j', strtotime($event['event_date']));
                        $eventDays[$dayNum] = true;
                        if (stripos($event['event_title'], 'exam') !== false) {
                            $eventColors[$dayNum] = '#dc3545';
                        } elseif (stripos($event['event_title'], 'holiday') !== false) {
                            $eventColors[$dayNum] = '#28a745';
                        } elseif (stripos($event['event_title'], 'meeting') !== false) {
                            $eventColors[$dayNum] = '#fd7e14';
                        } else {
                            $eventColors[$dayNum] = '#4a6fa5';
                        }
                    }
                ?>
                    <div style="border: 1px solid #dee2e6; border-radius: 5px; overflow: hidden; break-inside: avoid;">
                        <div style="background: #1a3a5c; color: white; padding: 5px; text-align: center; font-weight: bold; font-size: 12px;"><?= $monthNames[$month] ?></div>
                        <div style="display: grid; grid-template-columns: repeat(7, 1fr); background: #f8f9fa; font-size: 8px; text-align: center; padding: 3px;">
                            <span>M</span><span>T</span><span>W</span><span>T</span><span>F</span><span>S</span><span>S</span>
                        </div>
                        <div style="display: grid; grid-template-columns: repeat(7, 1fr);">
                            <?php
                            for ($i = 0; $i < $firstDayOfMonth; $i++) {
                                echo '<div style="min-height: 30px; border: 1px solid #f0f0f0; padding: 2px;"></div>';
                            }
                            for ($day = 1; $day <= $totalDays; $day++) {
                                $hasEvent = isset($eventDays[$day]);
                                $eventColor = $eventColors[$day] ?? '';
                                $eventType = '';
                                if ($eventColor == '#dc3545') $eventType = '📝';
                                elseif ($eventColor == '#28a745') $eventType = '🎉';
                                elseif ($eventColor == '#fd7e14') $eventType = '📋';
                                elseif ($eventColor == '#4a6fa5') $eventType = '📌';
                            ?>
                                <div style="min-height: 30px; border: 1px solid #f0f0f0; padding: 2px; <?= $hasEvent ? 'background: ' . $eventColor . '20;' : '' ?>">
                                    <div style="font-size: 8px; font-weight: bold;"><?= $day ?></div>
                                    <?php if ($hasEvent): ?>
                                        <div style="font-size: 7px; color: <?= $eventColor ?>;"><?= $eventType ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php
                            }
                            ?>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>

            <div class="events-list">
                <h5>Complete Yearly Events (<?= $currentYear ?>)</h5>
                <table class="events-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Event Title</th>
                            <th>Type</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($yearEvents as $event):
                            $eventType = 'General';
                            $rowClass = 'event-row-general';
                            if (stripos($event['event_title'], 'exam') !== false) {
                                $eventType = 'Examination';
                                $rowClass = 'event-row-exam';
                            } elseif (stripos($event['event_title'], 'holiday') !== false) {
                                $eventType = 'Holiday';
                                $rowClass = 'event-row-holiday';
                            } elseif (stripos($event['event_title'], 'meeting') !== false) {
                                $eventType = 'Meeting';
                                $rowClass = 'event-row-meeting';
                            }
                        ?>
                            <tr class="<?= $rowClass ?>">
                                <td><?= date('d M, Y', strtotime($event['event_date'])) ?></td>
                                <td><strong><?= htmlspecialchars($event['event_title']) ?></strong></td>
                                <td><?= $eventType ?></td>
                                <td><?= htmlspecialchars($event['description'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="legend">
                <div class="legend-item">
                    <div class="legend-color legend-color-exam"></div><span>Examination - Red (📝)</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color legend-color-holiday"></div><span>Holiday - Green (🎉)</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color legend-color-meeting"></div><span>Meeting - Orange (📋)</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color legend-color-general"></div><span>General Event - Blue (📌)</span>
                </div>
            </div>
        <?php endif; ?>

        <footer>
            <p>Official Academic Calendar - Smart Choice Academy | Generated on: <?= date('F d, Y h:i A') ?></p>
        </footer>
    </div>
</body>

</html>
<?php
ob_end_flush();
?>