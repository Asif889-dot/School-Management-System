<?php
// Admin - Parent Messages Management
require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();
requireRole('admin');

$db = $pdo;

// Handle delete
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    try {
        $db->prepare("DELETE FROM parent_messages WHERE id = ?")->execute([(int)$_GET['delete_id']]);
        header('Location: parent_messages.php?deleted=1');
        exit;
    } catch (PDOException $e) {
        header('Location: parent_messages.php?error=1');
        exit;
    }
}

// Handle POST (send message)
$message = '';
$messageType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send') {
    $receiver_id = !empty($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : null;
    $student_id = !empty($_POST['student_id']) ? (int)$_POST['student_id'] : null;
    $subject = trim($_POST['subject'] ?? '');
    $msg = trim($_POST['message'] ?? '');

    if (empty($msg)) {
        $message = 'Message cannot be empty.';
        $messageType = 'danger';
    } else {
        try {
            $stmt = $db->prepare("INSERT INTO parent_messages (sender_id, receiver_id, student_id, subject, message) VALUES (?,?,?,?,?)");
            $stmt->execute([$_SESSION['user_id'], $receiver_id, $student_id, $subject, $msg]);
            $message = 'Message sent successfully!';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Fetch messages
$messages = [];
try {
    $messages = $db->query("SELECT pm.*, su.full_name as sender_name, ru.full_name as receiver_name, s.first_name, s.last_name, s.reg_id
                            FROM parent_messages pm
                            LEFT JOIN users su ON pm.sender_id = su.id
                            LEFT JOIN users ru ON pm.receiver_id = ru.id
                            LEFT JOIN students s ON pm.student_id = s.id
                            ORDER BY pm.created_at DESC")->fetchAll();
} catch (PDOException $e) {}

// Fetch guardians
$guardians = [];
try { $guardians = $db->query("SELECT id, full_name, reg_id, email FROM users WHERE role='guardian' ORDER BY full_name")->fetchAll(); } catch (PDOException $e) {}

// Fetch students
$students = [];
try { $students = $db->query("SELECT id, first_name, last_name, reg_id FROM students WHERE status='approved' ORDER BY first_name")->fetchAll(); } catch (PDOException $e) {}

// Stats
$total = count($messages);
$unread = count(array_filter($messages, fn($m) => $m['parent_read'] == 0 && ($m['receiver_id'] ?? null) == null));
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Messages | Smart Choice Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .main-content { margin-left: 260px; padding: 20px; transition: all 0.3s; }
        @media (max-width: 768px) { .main-content { margin-left: 0; padding: 15px; } }
        .stats-card { background: white; border-radius: 12px; padding: 1rem; margin-bottom: 1rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center; }
        .stats-number { font-size: 1.8rem; font-weight: bold; color: #1a3a5c; }
        .card { border: none; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .card-header { background: white; border-bottom: 1px solid #e9ecef; font-weight: 600; }
        .table th { background: #f8f9fa; font-size: 12px; text-transform: uppercase; }
        .table td { font-size: 13px; vertical-align: middle; }
        .msg-item { border-left: 4px solid #4a6fa5; padding: 12px 15px; background: #f8f9fa; margin-bottom: 10px; border-radius: 8px; }
        .msg-unread { background: #e7f1ff; border-left-color: #084298; }
        .status-badge { padding: 3px 10px; border-radius: 20px; font-size: 11px; }
        .empty-state { text-align: center; padding: 40px; color: #6c757d; }
    </style>
</head>

<body>
    <?php include '../../backend/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                    <i class="bi bi-<?= $messageType == 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill' ?> me-2"></i><?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row mb-3">
                <div class="col-md-3"><div class="stats-card"><small class="text-muted">Total Messages</small><div class="stats-number"><?= $total ?></div></div></div>
                <div class="col-md-3"><div class="stats-card"><small class="text-muted">Guardians</small><div class="stats-number text-success"><?= count($guardians) ?></div></div></div>
                <div class="col-md-3"><div class="stats-card"><small class="text-muted">Replies / Unread</small><div class="stats-number text-warning"><?= $unread ?></div></div></div>
            </div>

            <div class="row">
                <div class="col-lg-4 mb-4">
                    <div class="card">
                        <div class="card-header p-3"><i class="bi bi-chat-dots text-primary me-2"></i>Send Message</div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="send">
                                <div class="mb-3"><label class="form-label fw-semibold">To (Guardian)</label>
                                    <select name="receiver_id" class="form-select">
                                        <option value="">Broadcast / No specific</option>
                                        <?php foreach ($guardians as $g): ?><option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['full_name']) ?> (<?= htmlspecialchars($g['reg_id']) ?>)</option><?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3"><label class="form-label fw-semibold">Related Student</label>
                                    <select name="student_id" class="form-select">
                                        <option value="">None</option>
                                        <?php foreach ($students as $st): ?><option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['first_name'] . ' ' . $st['last_name']) ?> (<?= htmlspecialchars($st['reg_id']) ?>)</option><?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3"><label class="form-label fw-semibold">Subject</label><input type="text" name="subject" class="form-control" placeholder="Subject"></div>
                                <div class="mb-3"><label class="form-label fw-semibold">Message <span class="text-danger">*</span></label><textarea name="message" class="form-control" rows="4" required></textarea></div>
                                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-send me-1"></i>Send Message</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header p-3 d-flex justify-content-between align-items-center"><span><i class="bi bi-envelope text-warning me-2"></i>Message Thread</span><span class="badge bg-primary"><?= $total ?></span></div>
                        <div class="card-body">
                            <?php if (empty($messages)): ?>
                                <div class="empty-state"><i class="bi bi-chat-square-text"></i><p class="mb-0">No messages yet.</p></div>
                            <?php else: ?>
                                <?php foreach ($messages as $m): ?>
                                    <div class="msg-item <?= ($m['parent_read'] ?? 0) == 0 && ($m['sender_id'] ?? 0) != $_SESSION['user_id'] ? 'msg-unread' : '' ?>">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <div class="d-flex align-items-center gap-2">
                                                    <strong><?= htmlspecialchars($m['sender_name'] ?? 'Unknown') ?></strong>
                                                    <i class="bi bi-arrow-right text-muted"></i>
                                                    <span><?= htmlspecialchars($m['receiver_name'] ?? 'All Guardians') ?></span>
                                                    <?php if (!empty($m['subject'])): ?><span class="badge bg-secondary"><?= htmlspecialchars($m['subject']) ?></span><?php endif; ?>
                                                </div>
                                                <?php if (!empty($m['first_name'])): ?><div class="small text-muted"><i class="bi bi-person me-1"></i><?= htmlspecialchars($m['first_name'] . ' ' . $m['last_name']) ?> (<?= htmlspecialchars($m['reg_id']) ?>)</div><?php endif; ?>
                                                <div class="mt-2"><?= nl2br(htmlspecialchars($m['message'])) ?></div>
                                                <div class="small text-muted mt-2"><i class="bi bi-clock me-1"></i><?= date('d M Y h:i A', strtotime($m['created_at'])) ?></div>
                                            </div>
                                            <a href="parent_messages.php?delete_id=<?= $m['id'] ?>" class="btn btn-sm text-danger" onclick="return confirm('Delete this message?')"><i class="bi bi-trash"></i></a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
