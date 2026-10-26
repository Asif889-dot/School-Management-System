<?php
// Guardian - Apply Leave Online

require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();

if ($_SESSION['role'] !== 'guardian') {
    header('Location: ../../index.php?error=unauthorized');
    exit;
}

$db = $pdo;
$guardian_user_id = $_SESSION['user_id'] ?? 0;
$guardian_name = $_SESSION['full_name'] ?? 'Parent';

// Get children
$children = [];
$stmt = $db->prepare("
    SELECT s.*, c.class_name, sec.section_name
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    WHERE s.status = 'approved'
      AND (s.guardian_user_id = ? OR s.father_name = ? OR s.mother_name = ?)
    ORDER BY s.id DESC
");
$stmt->execute([$guardian_user_id, $guardian_name, $guardian_name]);
$children = $stmt->fetchAll();

if (empty($children)) {
    $stmt = $db->prepare("
        SELECT s.*, c.class_name, sec.section_name
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        WHERE s.status = 'approved'
          AND (s.father_phone = ? OR s.mother_phone = ?)
        ORDER BY s.id DESC
    ");
    $stmt->execute([$_SESSION['reg_id'] ?? '', $_SESSION['reg_id'] ?? '']);
    $children = $stmt->fetchAll();
}

$message = '';
$messageType = '';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_leave'])) {
    $student_id = (int)$_POST['student_id'];
    $leave_type = $_POST['leave_type'] ?? 'personal';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $reason = trim($_POST['reason'] ?? '');

    // Validate student belongs to this guardian
    $valid_child = false;
    foreach ($children as $child) {
        if ($child['id'] == $student_id) {
            $valid_child = true;
            break;
        }
    }

    if (!$valid_child) {
        $message = 'Invalid student selected.';
        $messageType = 'danger';
    } elseif (empty($start_date) || empty($end_date)) {
        $message = 'Please select both start and end dates.';
        $messageType = 'danger';
    } elseif (strtotime($end_date) < strtotime($start_date)) {
        $message = 'End date cannot be before start date.';
        $messageType = 'danger';
    } else {
        try {
            $stmt = $db->prepare("
                INSERT INTO leave_applications (student_id, guardian_user_id, leave_type, start_date, end_date, reason, status)
                VALUES (?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([$student_id, $guardian_user_id, $leave_type, $start_date, $end_date, $reason]);
            $message = 'Leave application submitted successfully!';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Error submitting application: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Fetch leave history for this guardian's children
$leave_history = [];
if (!empty($children)) {
    $child_ids = array_column($children, 'id');
    $in = implode(',', array_fill(0, count($child_ids), '?'));
    try {
        $stmt = $db->prepare("
            SELECT la.*, s.first_name, s.last_name, s.reg_id
            FROM leave_applications la
            JOIN students s ON la.student_id = s.id
            WHERE la.student_id IN ($in)
            ORDER BY la.created_at DESC
        ");
        $stmt->execute($child_ids);
        $leave_history = $stmt->fetchAll();
    } catch (PDOException $e) {}
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply Leave - Smart Choice Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/teacher_sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background-color: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; display: flex; }
        .main-content { margin-left: 260px; padding: 20px 30px; min-height: 100vh; width: 100%; transition: all 0.3s ease; }
        @media (max-width: 992px) { .main-content { margin-left: 0; padding: 15px; } }
        .welcome-section { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 15px; padding: 20px 25px; margin-bottom: 25px; color: white; }
        .form-card { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 25px; }
        .form-card h5 { border-bottom: 2px solid #e9ecef; padding-bottom: 12px; margin-bottom: 20px; }
        .table-card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .table-card th { background: #f8f9fa; padding: 12px; font-size: 12px; font-weight: 700; text-transform: uppercase; }
        .table-card td { padding: 10px 12px; font-size: 13px; vertical-align: middle; }
        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .approved { background: #d4edda; color: #155724; }
        .pending { background: #fff3cd; color: #856404; }
        .rejected { background: #f8d7da; color: #721c24; }
        .empty-state { text-align: center; padding: 40px; color: #6c757d; }
        .empty-state i { font-size: 48px; opacity: 0.5; }
    </style>
</head>

<body>
    <?php include '../../backend/guardian_sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid">
            <div class="welcome-section">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="fw-bold mb-1"><i class="bi bi-calendar-x me-2"></i>Apply Leave</h4>
                        <p class="mb-0 opacity-75"><?= htmlspecialchars($guardian_name) ?> | Submit leave application for your child</p>
                    </div>
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                    <i class="bi bi-<?= $messageType == 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill' ?> me-2"></i><?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (empty($children)): ?>
                <div class="empty-state"><i class="bi bi-person-x"></i><h5>No children linked to your account</h5><p class="mb-0">Please contact the school administrator.</p></div>
            <?php else: ?>
                <div class="form-card">
                    <h5><i class="bi bi-send me-2 text-primary"></i>New Leave Application</h5>
                    <form method="POST">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Child</label>
                                <select name="student_id" class="form-select" required>
                                    <?php foreach ($children as $child): ?>
                                        <option value="<?= $child['id'] ?>"><?= htmlspecialchars($child['first_name'] . ' ' . $child['last_name'] . ' (' . $child['class_name'] . ')') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Leave Type</label>
                                <select name="leave_type" class="form-select">
                                    <option value="personal">Personal</option>
                                    <option value="sick">Sick</option>
                                    <option value="family">Family</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Start Date</label>
                                <input type="date" name="start_date" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">End Date</label>
                                <input type="date" name="end_date" class="form-control" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Reason</label>
                                <textarea name="reason" class="form-control" rows="3" placeholder="Please provide a reason for the leave..." required></textarea>
                            </div>
                            <div class="col-12">
                                <button type="submit" name="apply_leave" class="btn btn-primary">
                                    <i class="bi bi-send me-1"></i> Submit Application
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="table-card">
                    <div class="card-header p-3 bg-white border-bottom fw-semibold"><i class="bi bi-clock-history me-2"></i>Leave History</div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Child</th><th>Type</th><th>Start</th><th>End</th><th>Reason</th><th>Status</th></tr></thead>
                            <tbody>
                                <?php if (empty($leave_history)): ?>
                                    <tr><td colspan="6" class="text-center py-4 text-muted">No leave applications yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($leave_history as $leave): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name']) ?></td>
                                            <td><?= ucfirst($leave['leave_type']) ?></td>
                                            <td><?= date('d M Y', strtotime($leave['start_date'])) ?></td>
                                            <td><?= date('d M Y', strtotime($leave['end_date'])) ?></td>
                                            <td><?= htmlspecialchars(mb_substr($leave['reason'] ?? '', 0, 40)) ?></td>
                                            <td><span class="status-badge <?= $leave['status'] ?>"><?= ucfirst($leave['status']) ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
