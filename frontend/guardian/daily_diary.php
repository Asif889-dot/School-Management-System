<?php
// Guardian - View Child Daily Diary & Homework

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

$selected_student = isset($_GET['student_id']) ? (int)$_GET['student_id'] : (count($children) ? $children[0]['id'] : 0);

$valid_child = false;
foreach ($children as $child) {
    if ($child['id'] == $selected_student) $valid_child = true;
}
if (!$valid_child) $selected_student = count($children) ? $children[0]['id'] : 0;

// Determine selected child's class & section
$selected_class_id = 0;
$selected_section_id = 0;
foreach ($children as $child) {
    if ($child['id'] == $selected_student) {
        $selected_class_id = $child['class_id'];
        $selected_section_id = $child['section_id'];
    }
}

// Fetch diary entries
$diary_entries = [];
if ($selected_class_id > 0) {
    try {
        $stmt = $db->prepare("
            SELECT dd.*, c.class_name, sec.section_name, t.employee_id, u.full_name as teacher_name
            FROM daily_diary dd
            LEFT JOIN classes c ON dd.class_id = c.id
            LEFT JOIN sections sec ON dd.section_id = sec.id
            LEFT JOIN teachers t ON dd.teacher_id = t.teacher_id
            LEFT JOIN users u ON t.user_id = u.id
            WHERE dd.class_id = ? AND dd.section_id = ?
            ORDER BY dd.entry_date DESC, dd.id DESC
            LIMIT 30
        ");
        $stmt->execute([$selected_class_id, $selected_section_id]);
        $diary_entries = $stmt->fetchAll();
    } catch (PDOException $e) {
        $diary_entries = [];
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Diary - Smart Choice Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/teacher_sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background-color: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; display: flex; }
        .main-content { margin-left: 260px; padding: 20px 30px; min-height: 100vh; width: 100%; transition: all 0.3s ease; }
        @media (max-width: 992px) { .main-content { margin-left: 0; padding: 15px; } }
        .welcome-section { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 15px; padding: 20px 25px; margin-bottom: 25px; color: white; }
        .filter-card { background: white; border-radius: 12px; padding: 20px; margin-bottom: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .diary-card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .diary-header { background: #f8f9fa; padding: 15px 20px; border-bottom: 1px solid #e9ecef; }
        .diary-date { font-size: 12px; color: #6c757d; }
        .diary-body { padding: 20px; }
        .diary-section-title { font-size: 12px; font-weight: 700; text-transform: uppercase; color: #6c757d; margin-bottom: 6px; }
        .homework-box { background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px 15px; border-radius: 8px; margin-bottom: 15px; }
        .activities-box { background: #e7f1ff; border-left: 4px solid #4e73df; padding: 12px 15px; border-radius: 8px; }
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
                        <h4 class="fw-bold mb-1"><i class="bi bi-journal-bookmark me-2"></i>Daily Diary & Homework</h4>
                        <p class="mb-0 opacity-75"><?= htmlspecialchars($guardian_name) ?> | View child's daily diary</p>
                    </div>
                </div>
            </div>

            <?php if (empty($children)): ?>
                <div class="empty-state"><i class="bi bi-person-x"></i><h5>No children linked to your account</h5><p class="mb-0">Please contact the school administrator.</p></div>
            <?php else: ?>
                <div class="filter-card">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Select Child</label>
                            <select name="student_id" class="form-select" onchange="this.form.submit()">
                                <?php foreach ($children as $child): ?>
                                    <option value="<?= $child['id'] ?>" <?= $selected_student == $child['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($child['first_name'] . ' ' . $child['last_name'] . ' (' . $child['class_name'] . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>

                <?php if (empty($diary_entries)): ?>
                    <div class="empty-state"><i class="bi bi-journal-x"></i><h5>No diary entries yet</h5><p class="mb-0">Teachers have not added diary entries for this class.</p></div>
                <?php else: ?>
                    <?php foreach ($diary_entries as $entry): ?>
                        <div class="diary-card">
                            <div class="diary-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="fw-bold"><?= date('l, d M Y', strtotime($entry['entry_date'])) ?></span>
                                        <div class="diary-date">
                                            <?= htmlspecialchars($entry['class_name'] . ' - ' . $entry['section_name']) ?>
                                            <?php if (!empty($entry['teacher_name'])): ?> | <?= htmlspecialchars($entry['teacher_name']) ?><?php endif; ?>
                                        </div>
                                    </div>
                                    <?php if (!empty($entry['title'])): ?>
                                        <span class="badge bg-primary"><?= htmlspecialchars($entry['title']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="diary-body">
                                <?php if (!empty($entry['homework'])): ?>
                                    <div class="homework-box">
                                        <div class="diary-section-title"><i class="bi bi-pencil-square me-1"></i>Homework</div>
                                        <div class="small"><?= nl2br(htmlspecialchars($entry['homework'])) ?></div>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($entry['activities'])): ?>
                                    <div class="activities-box">
                                        <div class="diary-section-title"><i class="bi bi-stars me-1"></i>Activities</div>
                                        <div class="small"><?= nl2br(htmlspecialchars($entry['activities'])) ?></div>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($entry['notes']) && empty($entry['homework']) && empty($entry['activities'])): ?>
                                    <div class="small text-muted"><?= nl2br(htmlspecialchars($entry['notes'])) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
