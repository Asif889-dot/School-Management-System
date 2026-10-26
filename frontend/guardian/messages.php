<?php
// Guardian - Direct Messaging with Teachers

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

// Handle POST - send message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $receiver_id = (int)$_POST['receiver_id'];
    $student_id = (int)$_POST['student_id'];
    $subject = trim($_POST['subject'] ?? '');
    $msg_text = trim($_POST['message'] ?? '');

    if (empty($receiver_id) || empty($msg_text)) {
        $message = 'Please select a teacher and enter a message.';
        $messageType = 'danger';
    } else {
        try {
            $stmt = $db->prepare("
                INSERT INTO parent_messages (sender_id, receiver_id, student_id, subject, message, status, parent_read)
                VALUES (?, ?, ?, ?, ?, 'sent', 1)
            ");
            $stmt->execute([$guardian_user_id, $receiver_id, $student_id, $subject, $msg_text]);
            $message = 'Message sent successfully!';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Error sending message: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Handle mark read
if (isset($_GET['read']) && (int)$_GET['read'] > 0) {
    try {
        $stmt = $db->prepare("UPDATE parent_messages SET parent_read = 1 WHERE id = ? AND receiver_id = ?");
        $stmt->execute([(int)$_GET['read'], $guardian_user_id]);
    } catch (PDOException $e) {}
}

// Get list of teachers (potential receivers)
$teachers = [];
try {
    $stmt = $db->query("
        SELECT t.teacher_id, t.employee_id, u.id as user_id, u.full_name, u.email
        FROM teachers t
        JOIN users u ON t.user_id = u.id
        WHERE t.status = 'active'
        ORDER BY u.full_name
    ");
    $teachers = $stmt->fetchAll();
} catch (PDOException $e) {}

// Get messages for this guardian
$messages = [];
try {
    $stmt = $db->prepare("
        SELECT pm.*, 
               sender.full_name as sender_name, sender.role as sender_role,
               receiver.full_name as receiver_name,
               s.first_name, s.last_name
        FROM parent_messages pm
        LEFT JOIN users sender ON pm.sender_id = sender.id
        LEFT JOIN users receiver ON pm.receiver_id = receiver.id
        LEFT JOIN students s ON pm.student_id = s.id
        WHERE pm.receiver_id = ? OR pm.sender_id = ?
        ORDER BY pm.created_at DESC
    ");
    $stmt->execute([$guardian_user_id, $guardian_user_id]);
    $messages = $stmt->fetchAll();
} catch (PDOException $e) {}

// Unread count
$unread_count = 0;
foreach ($messages as $m) {
    if ($m['receiver_id'] == $guardian_user_id && $m['parent_read'] == 0) $unread_count++;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Smart Choice Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/teacher_sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background-color: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; display: flex; }
        .main-content { margin-left: 260px; padding: 20px 30px; min-height: 100vh; width: 100%; transition: all 0.3s ease; }
        @media (max-width: 992px) { .main-content { margin-left: 0; padding: 15px; } }
        .welcome-section { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 15px; padding: 20px 25px; margin-bottom: 25px; color: white; }
        .card { border: none; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .card-header { background: white; border-bottom: 1px solid #e9ecef; font-weight: 600; }
        .msg-item { padding: 15px 20px; border-bottom: 1px solid #f0f0f0; }
        .msg-item.unread { background: #f0f4ff; }
        .msg-sender { font-weight: 700; color: #2c3e50; }
        .msg-meta { font-size: 11px; color: #6c757d; }
        .msg-body { font-size: 14px; color: #495057; margin-top: 5px; }
        .msg-badge { font-size: 10px; padding: 2px 8px; border-radius: 20px; }
        .empty-state { text-align: center; padding: 40px; color: #6c757d; }
        .empty-state i { font-size: 48px; opacity: 0.5; }
        .avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #667eea, #764ba2); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; }
    </style>
</head>

<body>
    <?php include '../../backend/guardian_sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid">
            <div class="welcome-section">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="fw-bold mb-1"><i class="bi bi-chat-dots me-2"></i>Messages</h4>
                        <p class="mb-0 opacity-75"><?= htmlspecialchars($guardian_name) ?> | <?= $unread_count ?> unread message(s)</p>
                    </div>
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                    <i class="bi bi-<?= $messageType == 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill' ?> me-2"></i><?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Compose -->
                <div class="col-lg-5 mb-4">
                    <div class="card">
                        <div class="card-header p-3"><i class="bi bi-send text-primary me-2"></i>Compose Message</div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">To (Teacher)</label>
                                    <select name="receiver_id" class="form-select" required>
                                        <option value="">Select Teacher</option>
                                        <?php foreach ($teachers as $teacher): ?>
                                            <option value="<?= $teacher['user_id'] ?>"><?= htmlspecialchars($teacher['full_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Related Child (Optional)</label>
                                    <select name="student_id" class="form-select">
                                        <option value="">Select Child</option>
                                        <?php foreach ($children as $child): ?>
                                            <option value="<?= $child['id'] ?>"><?= htmlspecialchars($child['first_name'] . ' ' . $child['last_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Subject</label>
                                    <input type="text" name="subject" class="form-control" placeholder="Subject (optional)">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Message</label>
                                    <textarea name="message" class="form-control" rows="3" placeholder="Type your message..." required></textarea>
                                </div>
                                <button type="submit" name="send_message" class="btn btn-primary w-100"><i class="bi bi-send me-1"></i> Send Message</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Inbox -->
                <div class="col-lg-7 mb-4">
                    <div class="card">
                        <div class="card-header p-3 d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-inbox text-success me-2"></i>Message History</span>
                            <span class="badge bg-primary"><?= count($messages) ?></span>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($messages)): ?>
                                <div class="empty-state"><i class="bi bi-chat-left-text"></i><p class="mb-0">No messages yet</p></div>
                            <?php else: ?>
                                <?php foreach ($messages as $m): ?>
                                    <div class="msg-item <?= ($m['receiver_id'] == $guardian_user_id && $m['parent_read'] == 0) ? 'unread' : '' ?>">
                                        <div class="d-flex gap-3">
                                            <div class="avatar"><?= strtoupper(substr($m['sender_name'] ?? '?', 0, 1)) ?></div>
                                            <div class="flex-grow-1">
                                                <div class="d-flex justify-content-between">
                                                    <div>
                                                        <span class="msg-sender"><?= htmlspecialchars($m['sender_name'] ?? 'Unknown') ?></span>
                                                        <?php if ($m['sender_role'] == 'teacher'): ?>
                                                            <span class="badge bg-success ms-1">Teacher</span>
                                                        <?php elseif ($m['sender_role'] == 'guardian'): ?>
                                                            <span class="badge bg-warning ms-1">Parent</span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($m['first_name'])): ?>
                                                            <span class="msg-badge bg-info"><?= htmlspecialchars($m['first_name'] . ' ' . $m['last_name']) ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <span class="msg-meta"><?= date('d M Y h:i A', strtotime($m['created_at'])) ?></span>
                                                </div>
                                                <?php if (!empty($m['subject'])): ?>
                                                    <div class="fw-semibold small mt-1"><?= htmlspecialchars($m['subject']) ?></div>
                                                <?php endif; ?>
                                                <div class="msg-body"><?= nl2br(htmlspecialchars($m['message'])) ?></div>
                                                <?php if ($m['receiver_id'] == $guardian_user_id && $m['parent_read'] == 0): ?>
                                                    <a href="messages.php?read=<?= $m['id'] ?>" class="btn btn-sm btn-outline-secondary mt-2">Mark as read</a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
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
