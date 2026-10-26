<?php
require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();

if ($_SESSION['role'] !== 'guardian') {
    header('Location: ../../index.php?error=unauthorized');
    exit;
}

$db = $pdo;

// Get guardian user info
$guardian_user_id = $_SESSION['user_id'] ?? 0;
$guardian_name = $_SESSION['full_name'] ?? 'Parent';

// Find children linked to this guardian.
// Match by guardian_user_id if set, otherwise fall back to father/mother name & phone matching.
$children = [];
$stmt = $db->prepare("
    SELECT s.*, c.class_name, c.numeric_grade, sec.section_name, ay.year_name as academic_year
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    LEFT JOIN academic_years ay ON s.academic_year_id = ay.id
    WHERE s.status = 'approved'
      AND (
        s.guardian_user_id = ?
        OR s.father_name = ?
        OR s.mother_name = ?
      )
    ORDER BY s.id DESC
");
$stmt->execute([$guardian_user_id, $guardian_name, $guardian_name]);
$children = $stmt->fetchAll();

// Also try matching by phone if name didn't produce results
if (empty($children)) {
    $stmt = $db->prepare("
        SELECT s.*, c.class_name, c.numeric_grade, sec.section_name, ay.year_name as academic_year
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        LEFT JOIN academic_years ay ON s.academic_year_id = ay.id
        WHERE s.status = 'approved'
          AND (s.father_phone = ? OR s.mother_phone = ?)
        ORDER BY s.id DESC
    ");
    $stmt->execute([$_SESSION['reg_id'] ?? '', $_SESSION['reg_id'] ?? '']);
    $children = $stmt->fetchAll();
}

// Stats
$total_children = count($children);
$pending_leaves = 0;
$unread_messages = 0;

try {
    // Pending leave applications for this guardian's children
    $child_ids = array_column($children, 'id');
    if (!empty($child_ids)) {
        $in = implode(',', array_fill(0, count($child_ids), '?'));
        $stmt = $db->prepare("SELECT COUNT(*) as c FROM leave_applications WHERE student_id IN ($in) AND status = 'pending'");
        $stmt->execute($child_ids);
        $pending_leaves = (int)$stmt->fetchColumn();

        // Unread messages from teachers
        $stmt = $db->prepare("SELECT COUNT(*) as c FROM parent_messages WHERE receiver_id = ? AND parent_read = 0");
        $stmt->execute([$guardian_user_id]);
        $unread_messages = (int)$stmt->fetchColumn();
    }
} catch (PDOException $e) {
    $pending_leaves = 0;
    $unread_messages = 0;
}

// Recent notices
$notices = [];
try {
    $stmt = $db->query("
        SELECT * FROM notices 
        WHERE status = 'active' AND (target_audience = 'all' OR target_audience = 'parents')
        ORDER BY created_at DESC LIMIT 5
    ");
    $notices = $stmt->fetchAll();
} catch (PDOException $e) {
    $notices = [];
}

// Upcoming events
$events = [];
try {
    $stmt = $db->query("SELECT * FROM academic_calendar WHERE event_date >= CURDATE() ORDER BY event_date ASC LIMIT 5");
    $events = $stmt->fetchAll();
} catch (PDOException $e) {
    $events = [];
}

// Greeting
$hour = (int)date('H');
$greeting = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guardian Dashboard - Smart Choice Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/teacher_sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background-color: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; display: flex; }
        .main-content { margin-left: 260px; padding: 20px 30px; min-height: 100vh; width: 100%; transition: all 0.3s ease; }
        @media (max-width: 992px) { .main-content { margin-left: 0; padding: 15px; } }

        .welcome-section { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 15px; padding: 25px 30px; margin-bottom: 25px; color: white; }
        .welcome-title { font-size: 24px; font-weight: 700; margin-bottom: 5px; }
        .welcome-subtitle { font-size: 14px; opacity: 0.9; }

        .stat-card { background: white; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); transition: transform 0.2s; border-left: 4px solid; position: relative; }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-number { font-size: 32px; font-weight: 800; margin-bottom: 5px; }
        .stat-label { font-size: 13px; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-icon { font-size: 40px; opacity: 0.3; position: absolute; right: 15px; top: 15px; }

        .card { border: none; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .card-header { background: white; border-bottom: 1px solid #e9ecef; font-weight: 600; }
        .event-item { border-left: 3px solid #667eea; padding: 10px 15px; background: #f8f9fa; margin-bottom: 10px; border-radius: 8px; }
        .event-date { font-size: 11px; color: #6c757d; }
        .empty-state { text-align: center; padding: 30px; color: #6c757d; }
        .empty-state i { font-size: 48px; margin-bottom: 15px; opacity: 0.5; }
        .child-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .child-badge.grade { background: #e7f1ff; color: #084298; }
    </style>
</head>

<body>
    <?php include '../../backend/guardian_sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid">
            <!-- Welcome -->
            <div class="welcome-section">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="welcome-title"><?= $greeting ?>, <?= htmlspecialchars($guardian_name) ?>!</div>
                        <div class="welcome-subtitle">
                            <i class="bi bi-calendar3 me-1"></i> <?= date('l, F j, Y') ?> |
                            <i class="bi bi-clock ms-2 me-1"></i> <?= date('h:i A') ?>
                        </div>
                    </div>
                    <div><i class="bi bi-people display-4 opacity-50"></i></div>
                </div>
            </div>

            <!-- Stats -->
            <div class="row mb-4">
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card position-relative" style="border-left-color: #4e73df;">
                        <div class="stat-number"><?= $total_children ?></div>
                        <div class="stat-label">Children Enrolled</div>
                        <i class="bi bi-people stat-icon"></i>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card position-relative" style="border-left-color: #1cc88a;">
                        <div class="stat-number"><?= count($notices) ?></div>
                        <div class="stat-label">Active Notices</div>
                        <i class="bi bi-megaphone stat-icon"></i>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card position-relative" style="border-left-color: #f6c23e;">
                        <div class="stat-number"><?= $pending_leaves ?></div>
                        <div class="stat-label">Pending Leaves</div>
                        <i class="bi bi-calendar-x stat-icon"></i>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card position-relative" style="border-left-color: #36b9cc;">
                        <div class="stat-number"><?= $unread_messages ?></div>
                        <div class="stat-label">Unread Messages</div>
                        <i class="bi bi-chat-dots stat-icon"></i>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Children List -->
                <div class="col-lg-7 mb-4">
                    <div class="card">
                        <div class="card-header p-3">
                            <h5 class="mb-0"><i class="bi bi-people-fill text-primary me-2"></i>My Children</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($children)): ?>
                                <div class="empty-state">
                                    <i class="bi bi-person-x"></i>
                                    <p class="mb-0">No children linked to your account.</p>
                                    <small class="text-muted">Please contact the school administrator.</small>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Child</th>
                                                <th>Class</th>
                                                <th>Section</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($children as $child): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?= htmlspecialchars($child['first_name'] . ' ' . $child['last_name']) ?></strong>
                                                        <div><small class="text-muted"><?= htmlspecialchars($child['reg_id']) ?></small></div>
                                                    </td>
                                                    <td><span class="child-badge grade"><?= htmlspecialchars($child['class_name'] ?? 'N/A') ?></span></td>
                                                    <td><?= htmlspecialchars($child['section_name'] ?? '—') ?></td>
                                                    <td>
                                                        <a href="attendance.php?student_id=<?= $child['id'] ?>" class="btn btn-sm btn-outline-primary" title="Attendance"><i class="bi bi-calendar-check"></i></a>
                                                        <a href="marks.php?student_id=<?= $child['id'] ?>" class="btn btn-sm btn-outline-success" title="Marks"><i class="bi bi-bar-chart"></i></a>
                                                        <a href="fees.php?student_id=<?= $child['id'] ?>" class="btn btn-sm btn-outline-warning" title="Fees"><i class="bi bi-cash-coin"></i></a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="col-lg-5 mb-4">
                    <!-- Notices -->
                    <div class="card mb-4">
                        <div class="card-header p-3">
                            <h5 class="mb-0"><i class="bi bi-megaphone text-danger me-2"></i>School Notices</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($notices)): ?>
                                <div class="empty-state"><i class="bi bi-megaphone-fill"></i><p class="mb-0">No notices yet</p></div>
                            <?php else: ?>
                                <?php foreach ($notices as $notice): ?>
                                    <div class="event-item">
                                        <div class="event-date"><i class="bi bi-calendar3 me-1"></i><?= date('d M Y', strtotime($notice['created_at'])) ?></div>
                                        <div class="event-title mt-1 fw-semibold"><?= htmlspecialchars($notice['title']) ?></div>
                                        <?php if (!empty($notice['content'])): ?>
                                            <div class="small text-muted"><?= htmlspecialchars(mb_substr($notice['content'], 0, 100)) ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Upcoming Events -->
                    <div class="card">
                        <div class="card-header p-3">
                            <h5 class="mb-0"><i class="bi bi-calendar-event text-success me-2"></i>Upcoming Events</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($events)): ?>
                                <div class="empty-state"><i class="bi bi-calendar2-week"></i><p class="mb-0">No upcoming events</p></div>
                            <?php else: ?>
                                <?php foreach ($events as $event): ?>
                                    <div class="event-item">
                                        <div class="event-date"><i class="bi bi-calendar3 me-1"></i><?= date('l, d M Y', strtotime($event['event_date'])) ?></div>
                                        <div class="event-title mt-1 fw-semibold"><?= htmlspecialchars($event['event_title']) ?></div>
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
