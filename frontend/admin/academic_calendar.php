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

// Handle event deletion
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $deleteId = (int)$_GET['delete_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM academic_calendar WHERE id = ?");
        $stmt->execute([$deleteId]);
        header('Location: academic_calendar.php?deleted=1');
        exit();
    } catch (PDOException $e) {
        error_log("Delete error: " . $e->getMessage());
        header('Location: academic_calendar.php?error=1');
        exit();
    }
}

// Get current month and year
$currentMonth = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$currentYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Adjust for previous/next navigation
if (isset($_GET['prev'])) {
    $currentMonth--;
    if ($currentMonth < 1) {
        $currentMonth = 12;
        $currentYear--;
    }
}
if (isset($_GET['next'])) {
    $currentMonth++;
    if ($currentMonth > 12) {
        $currentMonth = 1;
        $currentYear++;
    }
}

// Fetch events for the current month
$startDate = date('Y-m-01', strtotime("$currentYear-$currentMonth-01"));
$endDate = date('Y-m-t', strtotime("$currentYear-$currentMonth-01"));

$events = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM academic_calendar WHERE event_date BETWEEN ? AND ? ORDER BY event_date ASC");
    $stmt->execute([$startDate, $endDate]);
    $events = $stmt->fetchAll();
} catch (PDOException $e) {
    $events = [];
}

// Organize events by date
$eventsByDate = [];
foreach ($events as $event) {
    $date = $event['event_date'];
    if (!isset($eventsByDate[$date])) {
        $eventsByDate[$date] = [];
    }
    $eventsByDate[$date][] = $event;
}

// Get first day of month and total days
$firstDayOfMonth = date('w', strtotime("$currentYear-$currentMonth-01"));
$totalDays = date('t', strtotime("$currentYear-$currentMonth-01"));

// Adjust for Monday as first day of week (0 = Sunday, so we shift)
$firstDayOfMonth = ($firstDayOfMonth == 0) ? 6 : $firstDayOfMonth - 1;

// Month names
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

// Get all upcoming events for sidebar
$upcomingEvents = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM academic_calendar WHERE event_date >= CURDATE() ORDER BY event_date ASC LIMIT 10");
    $stmt->execute();
    $upcomingEvents = $stmt->fetchAll();
} catch (PDOException $e) {
    $upcomingEvents = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Calendar | Smart Choice Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f0f2f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .main-content {
            margin-left: 260px;
            padding: 20px;
            transition: all 0.3s;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
        }

        /* Calendar Styles */
        .calendar-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .calendar-header {
            background: linear-gradient(135deg, #1a3a5c 0%, #0d2b46 100%);
            padding: 1.5rem;
            color: white;
        }

        .calendar-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .calendar-nav h2 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .calendar-nav .btn-nav {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            padding: 8px 20px;
            border-radius: 25px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .calendar-nav .btn-nav:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        .print-buttons {
            display: flex;
            gap: 10px;
            margin-left: auto;
        }

        .calendar-weekdays {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            background: #f8f9fa;
            border-bottom: 2px solid #e9ecef;
        }

        .weekday {
            padding: 15px;
            text-align: center;
            font-weight: 700;
            color: #495057;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .calendar-days {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            background: white;
        }

        .calendar-day {
            min-height: 120px;
            border-right: 1px solid #e9ecef;
            border-bottom: 1px solid #e9ecef;
            padding: 8px;
            position: relative;
            cursor: pointer;
            transition: all 0.2s;
            background: white;
        }

        .calendar-day:hover {
            background: #f8f9fa;
        }

        .calendar-day.other-month {
            background: #f8f9fa;
            color: #adb5bd;
        }

        .calendar-day.today {
            background: #fff9e6;
            border: 2px solid #f4a261;
        }

        .day-number {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 5px;
            display: inline-block;
            width: 30px;
            height: 30px;
            line-height: 30px;
            text-align: center;
            border-radius: 50%;
        }

        .today .day-number {
            background: #f4a261;
            color: #fff;
        }

        .event-badge {
            background: #4a6fa5;
            color: white;
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 10px;
            margin: 2px 0;
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }

        .event-badge:hover {
            background: #3a5a8c;
            transform: scale(1.02);
        }

        .event-badge-exam {
            background: #dc3545;
        }

        .event-badge-holiday {
            background: #28a745;
        }

        .event-badge-meeting {
            background: #fd7e14;
        }

        .more-events {
            font-size: 0.7rem;
            color: #4a6fa5;
            cursor: pointer;
            margin-top: 2px;
            font-weight: 500;
        }

        .more-events:hover {
            text-decoration: underline;
        }

        /* Sidebar Styles */
        .upcoming-events-sidebar {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 20px;
        }

        .upcoming-header {
            background: linear-gradient(135deg, #1a3a5c 0%, #0d2b46 100%);
            color: white;
            padding: 1rem;
        }

        .upcoming-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .upcoming-item {
            padding: 12px 15px;
            border-bottom: 1px solid #e9ecef;
            transition: all 0.2s;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .upcoming-item:hover {
            background: #f8f9fa;
        }

        .upcoming-info {
            flex: 1;
        }

        .upcoming-date {
            font-size: 0.75rem;
            color: #4a6fa5;
            font-weight: 600;
        }

        .upcoming-title {
            font-size: 0.85rem;
            font-weight: 500;
            margin-top: 3px;
        }

        .upcoming-delete {
            opacity: 0;
            transition: opacity 0.2s;
        }

        .upcoming-item:hover .upcoming-delete {
            opacity: 1;
        }

        /* Modal Styles */
        .event-list-modal .modal-body {
            max-height: 400px;
            overflow-y: auto;
        }

        .event-item {
            padding: 12px;
            border-left: 4px solid;
            margin-bottom: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .event-item:hover {
            background: #e9ecef;
            transform: translateX(3px);
        }

        .event-item-content {
            flex: 1;
        }

        .event-item-actions {
            display: flex;
            gap: 8px;
        }

        .event-item-exam {
            border-left-color: #dc3545;
        }

        .event-item-holiday {
            border-left-color: #28a745;
        }

        .event-item-meeting {
            border-left-color: #fd7e14;
        }

        .event-item-general {
            border-left-color: #4a6fa5;
        }

        .event-title {
            font-weight: 600;
            margin-bottom: 5px;
        }

        .event-date {
            font-size: 0.8rem;
            color: #6c757d;
        }

        /* Legend */
        .calendar-legend {
            display: flex;
            gap: 20px;
            margin-top: 15px;
            padding: 12px 15px;
            background: #f8f9fa;
            border-top: 1px solid #e9ecef;
            flex-wrap: wrap;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.75rem;
        }

        .legend-color {
            width: 14px;
            height: 14px;
            border-radius: 3px;
        }

        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .stats-number {
            font-size: 1.8rem;
            font-weight: bold;
            color: #1a3a5c;
        }

        .fab-add-event {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1a3a5c 0%, #0d2b46 100%);
            color: white;
            border: none;
            box-shadow: 0 4px 15px rgba(26, 58, 92, 0.4);
            cursor: pointer;
            transition: all 0.3s;
            z-index: 1000;
        }

        .fab-add-event:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(26, 58, 92, 0.6);
        }

        .fab-add-event i {
            font-size: 1.8rem;
        }

        .btn-delete {
            background: none;
            border: none;
            color: #dc3545;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 4px;
            transition: all 0.2s;
        }

        .btn-delete:hover {
            background: #dc3545;
            color: white;
        }

        .btn-edit {
            background: none;
            border: none;
            color: #fd7e14;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 4px;
            transition: all 0.2s;
        }

        .btn-edit:hover {
            background: #fd7e14;
            color: white;
        }
    </style>
</head>

<body>
    <?php include '../../backend/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <div class="row">
                <!-- Main Calendar Column -->
                <div class="col-lg-9">
                    <!-- Statistics Row -->
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <div class="stats-card">
                                <small class="text-muted">Total Events</small>
                                <div class="stats-number"><?= count($events) ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <small class="text-muted">Examinations</small>
                                <div class="stats-number">
                                    <?= count(array_filter($events, fn($e) => stripos($e['event_title'], 'exam') !== false)) ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <small class="text-muted">Holidays</small>
                                <div class="stats-number">
                                    <?= count(array_filter($events, fn($e) => stripos($e['event_title'], 'holiday') !== false)) ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <small class="text-muted">Meetings</small>
                                <div class="stats-number">
                                    <?= count(array_filter($events, fn($e) => stripos($e['event_title'], 'meeting') !== false)) ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Calendar -->
                    <div class="calendar-container">
                        <div class="calendar-header">
                            <div class="calendar-nav">
                                <a href="?prev=1&month=<?= $currentMonth ?>&year=<?= $currentYear ?>" class="btn-nav">
                                    <i class="bi bi-chevron-left"></i> Previous
                                </a>
                                <h2><?= $monthNames[$currentMonth] ?> <?= $currentYear ?></h2>
                                <div class="print-buttons">
                                    <a href="print_calendar.php?view=month&month=<?= $currentMonth ?>&year=<?= $currentYear ?>" class="btn-nav" target="_blank">
                                        <i class="bi bi-printer"></i> Print Month
                                    </a>
                                    <a href="print_calendar.php?view=year&year=<?= $currentYear ?>" class="btn-nav" target="_blank">
                                        <i class="bi bi-calendar2-week"></i> Print Year
                                    </a>
                                    <!-- In the calendar-nav div, add this button alongside the other print buttons -->
                                    <a href="print_calendar.php?view=bulk&year=<?= $currentYear ?>&bulk=all" class="btn-nav" target="_blank">
                                        <i class="bi bi-calendar2-week"></i> Print Full Year
                                    </a>
                                </div>
                                <a href="?next=1&month=<?= $currentMonth ?>&year=<?= $currentYear ?>" class="btn-nav">
                                    Next <i class="bi bi-chevron-right"></i>
                                </a>
                            </div>
                        </div>

                        <div class="calendar-weekdays">
                            <div class="weekday">Mon</div>
                            <div class="weekday">Tue</div>
                            <div class="weekday">Wed</div>
                            <div class="weekday">Thu</div>
                            <div class="weekday">Fri</div>
                            <div class="weekday">Sat</div>
                            <div class="weekday">Sun</div>
                        </div>

                        <div class="calendar-days">
                            <?php
                            $today = date('Y-m-d');

                            // Empty cells for days before month starts
                            for ($i = 0; $i < $firstDayOfMonth; $i++) {
                                echo '<div class="calendar-day other-month"></div>';
                            }

                            // Days of the month
                            for ($day = 1; $day <= $totalDays; $day++) {
                                $currentDate = sprintf("%04d-%02d-%02d", $currentYear, $currentMonth, $day);
                                $isToday = ($currentDate == $today);
                                $dayEvents = $eventsByDate[$currentDate] ?? [];
                                $hasEvents = !empty($dayEvents);
                                $eventCount = count($dayEvents);

                                $dayClass = 'calendar-day';
                                if ($isToday) $dayClass .= ' today';
                            ?>
                                <div class="<?= $dayClass ?>" onclick="openAddEventModal('<?= $currentDate ?>')">
                                    <div class="day-number"><?= $day ?></div>
                                    <?php if ($hasEvents): ?>
                                        <?php
                                        $displayEvents = array_slice($dayEvents, 0, 2);
                                        foreach ($displayEvents as $event):
                                            $eventClass = '';
                                            if (stripos($event['event_title'], 'exam') !== false) $eventClass = 'event-badge-exam';
                                            elseif (stripos($event['event_title'], 'holiday') !== false) $eventClass = 'event-badge-holiday';
                                            elseif (stripos($event['event_title'], 'meeting') !== false) $eventClass = 'event-badge-meeting';
                                        ?>
                                            <div class="event-badge <?= $eventClass ?>" onclick="event.stopPropagation(); viewEventDetails(<?= $event['id'] ?>, '<?= htmlspecialchars(addslashes($event['event_title'])) ?>', '<?= $event['event_date'] ?>', '<?= htmlspecialchars(addslashes($event['description'] ?? '')) ?>')">
                                                <?= htmlspecialchars(strlen($event['event_title']) > 22 ? substr($event['event_title'], 0, 20) . '...' : $event['event_title']) ?>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if ($eventCount > 2): ?>
                                            <div class="more-events" onclick="event.stopPropagation(); showAllEvents(<?= htmlspecialchars(json_encode($dayEvents)) ?>, '<?= $currentDate ?>')">
                                                +<?= ($eventCount - 2) ?> more
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            <?php
                            }

                            // Fill remaining cells
                            $remainingCells = (7 - (($firstDayOfMonth + $totalDays) % 7)) % 7;
                            for ($i = 0; $i < $remainingCells; $i++) {
                                echo '<div class="calendar-day other-month"></div>';
                            }
                            ?>
                        </div>

                        <!-- Legend -->
                        <div class="calendar-legend">
                            <div class="legend-item">
                                <div class="legend-color" style="background: #4a6fa5;"></div>
                                <span>General Event</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color" style="background: #dc3545;"></div>
                                <span>Examination</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color" style="background: #28a745;"></div>
                                <span>Holiday</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color" style="background: #fd7e14;"></div>
                                <span>Meeting</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color" style="background: #fff9e6; border: 2px solid #f4a261;"></div>
                                <span>Today</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Upcoming Events Sidebar -->
                <div class="col-lg-3">
                    <div class="upcoming-events-sidebar">
                        <div class="upcoming-header">
                            <h6 class="mb-0"><i class="bi bi-calendar-event"></i> Upcoming Events</h6>
                        </div>
                        <div class="upcoming-list">
                            <?php if (empty($upcomingEvents)): ?>
                                <div class="text-center p-4 text-muted">
                                    <i class="bi bi-calendar2-week fs-1"></i>
                                    <p class="mt-2 mb-0">No upcoming events</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($upcomingEvents as $event): ?>
                                    <div class="upcoming-item">
                                        <div class="upcoming-info" onclick="viewEventDetails(<?= $event['id'] ?>, '<?= htmlspecialchars(addslashes($event['event_title'])) ?>', '<?= $event['event_date'] ?>', '<?= htmlspecialchars(addslashes($event['description'] ?? '')) ?>')">
                                            <div class="upcoming-date">
                                                <i class="bi bi-calendar3"></i> <?= date('D, M j, Y', strtotime($event['event_date'])) ?>
                                            </div>
                                            <div class="upcoming-title">
                                                <?= htmlspecialchars($event['event_title']) ?>
                                            </div>
                                        </div>
                                        <button class="upcoming-delete btn-delete" onclick="confirmDelete(<?= $event['id'] ?>, '<?= htmlspecialchars(addslashes($event['event_title'])) ?>')" title="Delete event">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="p-2 text-center border-top">
                            <button class="btn btn-sm btn-outline-primary w-100" onclick="openAddEventModal('')">
                                <i class="bi bi-plus-circle"></i> Add Event
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Floating Action Button -->
    <button class="fab-add-event" onclick="openAddEventModal('')">
        <i class="bi bi-plus-lg"></i>
    </button>

    <!-- Add Event Modal (Popup) -->
    <div class="modal fade" id="addEventModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-calendar-plus"></i> Add Academic Event
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="../../backend/add_event.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="selected_date" id="modalSelectedDate">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Event Date</label>
                            <input type="text" class="form-control" id="displayDate" readonly style="background: #f8f9fa;">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Event Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" placeholder="Enter event title" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Event Type</label>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-secondary flex-grow-1 type-btn" data-type="general" onclick="setEventType('general')">General</button>
                                <button type="button" class="btn btn-outline-danger flex-grow-1 type-btn" data-type="exam" onclick="setEventType('exam')">Exam</button>
                                <button type="button" class="btn btn-outline-success flex-grow-1 type-btn" data-type="holiday" onclick="setEventType('holiday')">Holiday</button>
                                <button type="button" class="btn btn-outline-warning flex-grow-1 type-btn" data-type="meeting" onclick="setEventType('meeting')">Meeting</button>
                            </div>
                            <input type="hidden" name="event_type" id="modalEventType" value="general">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Description</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Event description (optional)"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Event</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Event Details Modal with Delete Option -->
    <div class="modal fade" id="eventDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Event Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="eventDetailsContent"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" id="deleteFromModalBtn" onclick="deleteFromModal()">Delete Event</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- All Events Modal for a specific date -->
    <div class="modal fade" id="allEventsModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Events for <span id="eventsDateTitle"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body event-list-modal" id="allEventsList">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" onclick="openAddEventModalFromModal()">Add New Event</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the event: <strong id="deleteEventTitle"></strong>?</p>
                    <p class="text-muted small">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Delete</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
        <div class="modal fade" id="successModal" tabindex="-1" data-bs-backdrop="static">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">Success!</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-center py-4">
                        <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
                        <p class="mt-3">Event has been added successfully to the academic calendar.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
                    </div>
                </div>
            </div>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var successModal = new bootstrap.Modal(document.getElementById('successModal'));
                successModal.show();
                setTimeout(function() {
                    window.history.replaceState({}, document.title, window.location.pathname);
                }, 100);
            });
        </script>
    <?php endif; ?>

    <!-- Delete Success Modal -->
    <?php if (isset($_GET['deleted']) && $_GET['deleted'] == 1): ?>
        <div class="modal fade" id="deleteSuccessModal" tabindex="-1" data-bs-backdrop="static">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title">Event Deleted</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-center py-4">
                        <i class="bi bi-trash-fill text-danger" style="font-size: 3rem;"></i>
                        <p class="mt-3">The event has been successfully deleted from the calendar.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">OK</button>
                    </div>
                </div>
            </div>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var deleteSuccessModal = new bootstrap.Modal(document.getElementById('deleteSuccessModal'));
                deleteSuccessModal.show();
                setTimeout(function() {
                    window.history.replaceState({}, document.title, window.location.pathname);
                }, 100);
            });
        </script>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentEventDate = '';
        let currentDeleteId = null;

        function openAddEventModal(date) {
            currentEventDate = date;
            const modal = new bootstrap.Modal(document.getElementById('addEventModal'));

            if (date) {
                const formattedDate = formatDate(date);
                document.getElementById('displayDate').value = formattedDate;
                document.getElementById('modalSelectedDate').value = date;
            } else {
                document.getElementById('displayDate').value = 'Select a date from calendar';
                document.getElementById('modalSelectedDate').value = '';
            }

            modal.show();
        }

        function openAddEventModalFromModal() {
            const modal = bootstrap.Modal.getInstance(document.getElementById('allEventsModal'));
            modal.hide();
            openAddEventModal(currentEventDate);
        }

        function setEventType(type) {
            document.getElementById('modalEventType').value = type;

            const buttons = document.querySelectorAll('.type-btn');
            buttons.forEach(btn => {
                btn.classList.remove('active');
                if (btn.getAttribute('data-type') === type) {
                    btn.classList.add('active');
                    if (type === 'general') btn.style.background = '#e8f0fe';
                    else if (type === 'exam') btn.style.background = '#fee';
                    else if (type === 'holiday') btn.style.background = '#e8f5e9';
                    else if (type === 'meeting') btn.style.background = '#fff3e0';
                } else {
                    btn.style.background = '';
                }
            });
        }

        function viewEventDetails(id, title, date, description) {
            currentDeleteId = id;
            const modal = new bootstrap.Modal(document.getElementById('eventDetailsModal'));
            const content = `
                <div class="mb-3">
                    <strong>Event Title:</strong>
                    <p class="mt-1">${escapeHtml(title)}</p>
                </div>
                <div class="mb-3">
                    <strong>Date:</strong>
                    <p class="mt-1">${formatDate(date)}</p>
                </div>
                ${description ? `
                <div class="mb-3">
                    <strong>Description:</strong>
                    <p class="mt-1">${escapeHtml(description)}</p>
                </div>
                ` : ''}
                <div class="text-muted small mt-3 pt-2 border-top">
                    <i class="bi bi-clock"></i> Added to academic calendar
                </div>
            `;
            document.getElementById('eventDetailsContent').innerHTML = content;
            modal.show();
        }

        function deleteFromModal() {
            const modal = bootstrap.Modal.getInstance(document.getElementById('eventDetailsModal'));
            modal.hide();
            if (currentDeleteId) {
                window.location.href = `?delete_id=${currentDeleteId}`;
            }
        }

        function confirmDelete(id, title) {
            currentDeleteId = id;
            document.getElementById('deleteEventTitle').innerText = title;
            document.getElementById('confirmDeleteBtn').href = `?delete_id=${id}`;
            const modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
            modal.show();
        }

        function showAllEvents(events, date) {
            currentEventDate = date;
            document.getElementById('eventsDateTitle').innerHTML = formatDate(date);

            let html = '';
            events.forEach(event => {
                let eventClass = 'event-item-general';
                if (event.event_title.toLowerCase().includes('exam')) eventClass = 'event-item-exam';
                else if (event.event_title.toLowerCase().includes('holiday')) eventClass = 'event-item-holiday';
                else if (event.event_title.toLowerCase().includes('meeting')) eventClass = 'event-item-meeting';

                html += `
                    <div class="event-item ${eventClass}">
                        <div class="event-item-content" onclick="viewEventDetails(${event.id}, '${escapeHtml(event.event_title)}', '${event.event_date}', '${escapeHtml(event.description || '')}')">
                            <div class="event-title">${escapeHtml(event.event_title)}</div>
                            <div class="event-date">${formatDate(event.event_date)}</div>
                            ${event.description ? `<div class="small text-muted mt-1">${escapeHtml(event.description)}</div>` : ''}
                        </div>
                        <div class="event-item-actions">
                            <button class="btn-delete" onclick="event.stopPropagation(); confirmDelete(${event.id}, '${escapeHtml(event.event_title)}')" title="Delete event">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                `;
            });

            document.getElementById('allEventsList').innerHTML = html;
            const modal = new bootstrap.Modal(document.getElementById('allEventsModal'));
            modal.show();
        }

        function formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }

        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/[&<>]/g, function(m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            setEventType('general');
        });
    </script>
</body>

</html>
<?php
ob_end_flush();
