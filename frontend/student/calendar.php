<?php
// Student - View Academic Calendar

require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();

if ($_SESSION['role'] !== 'student') {
    header('Location: ../../index.php?error=unauthorized');
    exit;
}

$db = $pdo;

// Fetch events
$events = [];
try {
    $events = $db->query("SELECT * FROM academic_calendar ORDER BY event_date ASC")->fetchAll();
} catch (PDOException $e) {}

// Group by month
$grouped = [];
foreach ($events as $e) {
    $month_key = date('Y-m', strtotime($e['event_date']));
    $grouped[$month_key][] = $e;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Calendar - Smart Choice Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/teacher_sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background-color: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; display: flex; }
        .main-content { margin-left: 260px; padding: 20px 30px; min-height: 100vh; width: 100%; transition: all 0.3s ease; }
        @media (max-width: 992px) { .main-content { margin-left: 0; padding: 15px; } }
        .welcome-section { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 15px; padding: 20px 25px; margin-bottom: 25px; color: white; }
        .card { border: none; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .card-header { background: white; border-bottom: 1px solid #e9ecef; font-weight: 600; }
        .event-item { border-left: 3px solid #667eea; padding: 12px 15px; background: #f8f9fa; margin-bottom: 10px; border-radius: 8px; }
        .event-date { font-size: 11px; color: #6c757d; }
        .event-title { font-size: 14px; font-weight: 600; color: #333; }
        .month-badge { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 8px 20px; border-radius: 30px; font-weight: 700; display: inline-block; margin-bottom: 15px; }
        .empty-state { text-align: center; padding: 60px; color: #6c757d; }
        .empty-state i { font-size: 64px; opacity: 0.5; }
    </style>
</head>

<body>
    <?php include '../../backend/student_sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid">
            <div class="welcome-section">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="fw-bold mb-1"><i class="bi bi-calendar-month me-2"></i>Academic Calendar</h4>
                        <p class="mb-0 opacity-75">Upcoming events and important dates</p>
                    </div>
                </div>
            </div>

            <?php if (empty($events)): ?>
                <div class="empty-state"><i class="bi bi-calendar2-week"></i><h4>No events published</h4><p class="mb-0">Check back later for upcoming events.</p></div>
            <?php else: ?>
                <?php foreach ($grouped as $month_key => $month_events): ?>
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="text-center"><span class="month-badge"><?= date('F Y', strtotime($month_key)) ?></span></div>
                            <?php foreach ($month_events as $e): ?>
                                <div class="event-item">
                                    <div class="event-date"><i class="bi bi-calendar3 me-1"></i><?= date('l, d M Y', strtotime($e['event_date'])) ?></div>
                                    <div class="event-title"><?= htmlspecialchars($e['event_title']) ?></div>
                                    <?php if (!empty($e['description'])): ?>
                                        <div class="event-title small text-muted mt-1"><?= nl2br(htmlspecialchars($e['description'])) ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
