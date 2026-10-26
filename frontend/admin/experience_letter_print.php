<?php
// Admin - Print Experience Letter
require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();
requireRole('admin');

$db = $pdo;
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$letter = null;
if ($id > 0) {
    $stmt = $db->prepare("SELECT el.*, t.employee_id, t.gender, t.qualification, u.full_name
                          FROM experience_letters el
                          JOIN teachers t ON el.teacher_id = t.teacher_id
                          JOIN users u ON t.user_id = u.id
                          WHERE el.id = ?");
    $stmt->execute([$id]);
    $letter = $stmt->fetch();
}
if (!$letter) { die('Letter not found.'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Experience Letter - <?= htmlspecialchars($letter['full_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Times New Roman', serif; background: #f0f2f5; }
        .page { max-width: 800px; margin: 20px auto; background: white; padding: 50px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .letter-head { text-align: center; border-bottom: 3px solid #1a3a5c; padding-bottom: 15px; margin-bottom: 30px; }
        .letter-head h2 { color: #1a3a5c; margin: 0; }
        .letter-no { text-align: right; font-size: 14px; margin-bottom: 20px; }
        .date { text-align: right; font-size: 14px; margin-bottom: 30px; }
        .subject { text-align: center; font-weight: bold; text-decoration: underline; margin-bottom: 30px; font-size: 16px; }
        .content { font-size: 15px; line-height: 1.8; text-align: justify; }
        .signature { margin-top: 60px; }
        .no-print { text-align: center; margin: 20px; }
    </style>
</head>
<body>
    <div class="no-print"><button class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer"></i> Print</button></div>
    <div class="page">
        <div class="letter-head">
            <h2>Smart Choice Academy</h2>
            <div>Excellence in Education</div>
        </div>
        <?php if (!empty($letter['letter_no'])): ?><div class="letter-no">Letter No: <?= htmlspecialchars($letter['letter_no']) ?></div><?php endif; ?>
        <div class="date">Date: <?= date('d F Y', strtotime($letter['issued_date'] ?? $letter['generated_at'])) ?></div>
        <div class="subject">TO WHOM IT MAY CONCERN</div>
        <div class="content">
            <?= nl2br(htmlspecialchars($letter['content'])) ?>
        </div>
        <div class="signature">
            _________________________<br>
            <strong>Principal</strong><br>
            Smart Choice Academy
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css"></script>
</body>
</html>
