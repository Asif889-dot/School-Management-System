<?php

require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();

if ($_SESSION['role'] !== 'teacher') {
    header('Location: ../../index.php?error=unauthorized');
    exit;
}

$db = $pdo;
$teacher_user_id = $_SESSION['user_id'] ?? 0;
$teacher_name = $_SESSION['full_name'] ?? 'Teacher';

// Handle reply
$message = '';
$messageType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reply'])) {
    $reply_to = (int)$_POST['reply_to'];
    $receiver_id = (int)$_POST['receiver_id'];
    $student_id = !empty($_POST['student_id']) ? (int)$_POST['student_id'] : null;
    $subject = trim($_POST['subject'] ?? '');
    $reply_text = trim($_POST['message'] ?? '');

    if (empty($reply_to) || empty($reply_text)) {
        $message = 'Please provide a reply message.';
        $messageType = 'danger';
    } else {
        try {
            $stmt = $db->prepare("
                INSERT INTO parent_messages (sender_id, receiver_id, student_id, subject, message, status, reply_to, teacher_read)
                VALUES (?, ?, ?, ?, ?, 'replied', ?, 1)
            ");
            $stmt->execute([$teacher_user_id, $receiver_id, $student_id, $subject, $reply_text, $reply_to]);
            // Mark original as replied
            $stmt = $db->prepare("UPDATE parent_messages SET status = 'replied', teacher_read = 1 WHERE id = ?");
            $stmt->execute([$reply_to]);
            $message = 'Reply sent successfully!';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Error sending reply: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Handle mark read
if (isset($_GET['read']) && (int)$_GET['read'] > 0) {
    try {
        $db->prepare("UPDATE parent_messages SET teacher_read = 1 WHERE id = ? AND receiver_id = ?")->execute([(int)$_GET['read'], $teacher_user_id]);
        header('Location: reply_messages.php');
        exit;
    } catch (PDOException $e) {}
}

// Get messages involving this teacher (received from parents)
$messages = [];
try {
    $stmt = $db->prepare("
        SELECT pm.*,
               sender.full_name as sender_name, sender.role as sender_role,
               s.first_name, s.last_name,
               reply.message as reply_message
        FROM parent_messages pm
        LEFT JOIN users sender ON pm.sender_id = sender.id
        LEFT JOIN students s ON pm.student_id = s.id
        LEFT JOIN parent_messages reply ON pm.reply_to = reply.id
        WHERE pm.receiver_id = ? OR pm.sender_id = ?
        ORDER BY pm.created_at DESC
    ");
    $stmt->execute([$teacher_user_id, $teacher_user_id]);
    $messages = $stmt->fetchAll();
} catch (PDOException $e) {}

// Unread count
$unread_count = 0;
foreach ($messages as $m) {
    if ($m['receiver_id'] == $teacher_user_id && $m['teacher_read'] == 0) $unread_count++;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Messages - Smart Choice Academy</title>
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
        .avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #667eea, #764ba2); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; }
        .reply-box { background: #f8f9fa; border-left: 3px solid #667eea; padding: 10px 15px; margin-top: 10px; border-radius: 8px; }
        .empty-state { text-align: center; padding: 40px; color: #6c757d; }
        .empty-state i { font-size: 48px; opacity: 0.5; }
    </style>
</head>

<body>
    <?php include '../../backend/teacher_sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid">
            <div class="welcome-section">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="fw-bold mb-1"><i class="bi bi-chat-dots me-2"></i>Parent Messages</h4>
                        <p class="mb-0 opacity-75"><?= htmlspecialchars($teacher_name) ?> | <?= $unread_count ?> unread message(s)</p>
                    </div>
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                    <i class="bi bi-<?= $messageType == 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill' ?> me-2"></i><?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header p-3 d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-inbox text-success me-2"></i>Message History</span>
                    <span class="badge bg-primary"><?= count($messages) ?></span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($messages)): ?>
                        <div class="empty-state"><i class="bi bi-chat-left-text"></i><p class="mb-0">No messages from parents yet.</p></div>
                    <?php else: ?>
                        <?php foreach ($messages as $m): ?>
                            <div class="msg-item <?= ($m['receiver_id'] == $teacher_user_id && $m['teacher_read'] == 0) ? 'unread' : '' ?>">
                                <div class="d-flex gap-3">
                                    <div class="avatar"><?= strtoupper(substr($m['sender_name'] ?? '?', 0, 1)) ?></div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <span class="msg-sender"><?= htmlspecialchars($m['sender_name'] ?? 'Unknown') ?></span>
                                                <?php if (($m['sender_role'] ?? '') == 'guardian'): ?>
                                                    <span class="badge bg-warning ms-1">Parent</span>
                                                <?php elseif (($m['sender_role'] ?? '') == 'teacher'): ?>
                                                    <span class="badge bg-success ms-1">Teacher</span>
                                                <?php endif; ?>
                                                <?php if (!empty($m['first_name'])): ?>
                                                    <span class="badge bg-info ms-1"><?= htmlspecialchars($m['first_name'] . ' ' . $m['last_name']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <span class="msg-meta"><?= date('d M Y h:i A', strtotime($m['created_at'])) ?></span>
                                        </div>
                                        <?php if (!empty($m['subject'])): ?>
                                            <div class="fw-semibold small mt-1"><?= htmlspecialchars($m['subject']) ?></div>
                                        <?php endif; ?>
                                        <div class="msg-body"><?= nl2br(htmlspecialchars($m['message'])) ?></div>

                                        <!-- Reply box -->
                                        <div class="reply-box">
                                            <form method="POST" class="d-flex gap-2 align-items-start">
                                                <input type="hidden" name="send_reply" value="1">
                                                <input type="hidden" name="reply_to" value="<?= $m['id'] ?>">
                                                <input type="hidden" name="receiver_id" value="<?= $m['sender_id'] ?>">
                                                <input type="hidden" name="student_id" value="<?= $m['student_id'] ?>">
                                                <input type="hidden" name="subject" value="Re: <?= htmlspecialchars($m['subject'] ?? 'Message') ?>">
                                                <textarea name="message" class="form-control form-control-sm" rows="1" placeholder="Type reply..." required></textarea>
                                                <?php if ($m['receiver_id'] == $teacher_user_id && $m['teacher_read'] == 0): ?>
                                                    <a href="reply_messages.php?read=<?= $m['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Mark as read"><i class="bi bi-check2"></i></a>
                                                <?php endif; ?>
                                                <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-send"></i></button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
