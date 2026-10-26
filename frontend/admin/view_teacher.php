<?php
require_once '../../backend/auth.php';
require_once '../../backend/config.php';

// Access Control: Ensure only authorized users can view teacher profiles
requireLogin();

$teacher_id = $_GET['id'] ?? 0;
$teacher = null;
$courses = [];

try {
    // 1. Fetch Detailed Teacher Profile Data
    $stmt = $pdo->prepare("
        SELECT t.*, u.email, u.full_name 
        FROM teachers t
        JOIN users u ON t.user_id = u.id
        WHERE t.teacher_id = ?
    ");
    $stmt->execute([$teacher_id]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Fetch Assigned Courses Workload for this specific teacher
    // Note: If you add a teacher_courses mapping table, adjust this query accordingly
    $courseStmt = $pdo->prepare("
        SELECT c.course_name, c.course_code, cl.class_name 
        FROM courses c
        JOIN classes cl ON c.class_id = cl.id
        WHERE c.class_id IN (SELECT class_id FROM classes) 
    ");
    $courseStmt->execute();
    $courses = $courseStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error = "System Query Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Teacher Profile | Smart Choice Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <?php if ($teacher): ?>
            <div class="card shadow-sm p-4">
                <h2 class="fw-bold mb-4"><?= htmlspecialchars($teacher['full_name']) ?></h2>
                <div class="row">
                    <div class="col-md-4 text-center">
                        <img src="<?= htmlspecialchars($teacher['photo'] ?? '../../assets/img/default-avatar.png') ?>" class="img-fluid rounded mb-3" style="max-width: 200px;">
                    </div>
                    <div class="col-md-8">
                        <table class="table table-borderless">
                            <tr><th>Employee ID:</th><td><?= htmlspecialchars($teacher['employee_id']) ?></td></tr>
                            <tr><th>Qualification:</th><td><?= htmlspecialchars($teacher['qualification']) ?></td></tr>
                            <tr><th>Contact:</th><td><?= htmlspecialchars($teacher['phone']) ?></td></tr>
                            <tr><th>Status:</th><td><span class="badge bg-success"><?= htmlspecialchars($teacher['status']) ?></span></td></tr>
                        </table>
                    </div>
                </div>

                <h4 class="mt-4">Academic Workload Matrix</h4>
                <table class="table table-hover mt-3">
                    <thead class="table-light">
                        <tr><th>Course Code</th><th>Course Name</th><th>Class Association</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($courses as $c): ?>
                            <tr>
                                <td><?= htmlspecialchars($c['course_code']) ?></td>
                                <td><?= htmlspecialchars($c['course_name']) ?></td>
                                <td><?= htmlspecialchars($c['class_name']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-danger">Teacher profile not found in system record.</div>
        <?php endif; ?>
    </div>
</body>
</html>