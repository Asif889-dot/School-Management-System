<?php
// Admin - Salary Slip Generation
require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();
requireRole('admin');

$db = $pdo;

// Handle delete
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    try {
        $db->prepare("DELETE FROM salary_slips WHERE id = ?")->execute([(int)$_GET['delete_id']]);
        header('Location: salary_slips.php?deleted=1');
        exit;
    } catch (PDOException $e) {
        header('Location: salary_slips.php?error=1');
        exit;
    }
}

// Handle POST (generate slip)
$message = '';
$messageType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $teacher_id = (int)$_POST['teacher_id'];
    $slip_month = trim($_POST['slip_month'] ?? '');
    $basic_salary = (float)($_POST['basic_salary'] ?? 0);
    $allowances = (float)($_POST['allowances'] ?? 0);
    $deductions = (float)($_POST['deductions'] ?? 0);
    $net_salary = $basic_salary + $allowances - $deductions;

    if ($teacher_id <= 0 || empty($slip_month)) {
        $message = 'Select teacher and month.';
        $messageType = 'danger';
    } else {
        try {
            $stmt = $db->prepare("INSERT INTO salary_slips (teacher_id, slip_month, basic_salary, allowances, deductions, net_salary, generated_by) VALUES (?,?,?,?,?,?,?)
                                  ON DUPLICATE KEY UPDATE basic_salary=VALUES(basic_salary), allowances=VALUES(allowances), deductions=VALUES(deductions), net_salary=VALUES(net_salary), generated_by=VALUES(generated_by)");
            $stmt->execute([$teacher_id, $slip_month, $basic_salary, $allowances, $deductions, $net_salary, $_SESSION['user_id']]);
            $message = 'Salary slip generated successfully!';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Fetch teachers
$teachers = [];
try {
    $teachers = $db->query("SELECT t.teacher_id, t.employee_id, t.salary, u.full_name, u.email
                            FROM teachers t JOIN users u ON t.user_id = u.id
                            WHERE t.status = 'active' ORDER BY u.full_name")->fetchAll();
} catch (PDOException $e) {}

// Fetch salary slips
$slips = [];
try {
    $month_filter = $_GET['month'] ?? '';
    $query = "SELECT ss.*, t.employee_id, u.full_name
              FROM salary_slips ss
              JOIN teachers t ON ss.teacher_id = t.teacher_id
              JOIN users u ON t.user_id = u.id";
    if (!empty($month_filter)) $query .= " WHERE ss.slip_month = '$month_filter'";
    $query .= " ORDER BY ss.slip_month DESC, u.full_name";
    $slips = $db->query($query)->fetchAll();
} catch (PDOException $e) {}

// Get months available
$months = [];
foreach ($slips as $s) { if (!in_array($s['slip_month'], $months)) $months[] = $s['slip_month']; }
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Slips | Smart Choice Academy</title>
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
        .empty-state { text-align: center; padding: 40px; color: #6c757d; }
        .slip-item { border-left: 4px solid #4a6fa5; padding: 12px 15px; background: #f8f9fa; margin-bottom: 10px; border-radius: 8px; }
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
                <div class="col-md-3"><div class="stats-card"><small class="text-muted">Total Slips</small><div class="stats-number"><?= count($slips) ?></div></div></div>
                <div class="col-md-3"><div class="stats-card"><small class="text-muted">Active Teachers</small><div class="stats-number text-success"><?= count($teachers) ?></div></div></div>
                <div class="col-md-3"><div class="stats-card"><small class="text-muted">Total Payroll</small><div class="stats-number text-primary">Rs. <?= number_format(array_sum(array_column($slips, 'net_salary')), 0) ?></div></div></div>
            </div>

            <div class="row">
                <div class="col-lg-4 mb-4">
                    <div class="card">
                        <div class="card-header p-3"><i class="bi bi-file-earmark-text text-primary me-2"></i>Generate Salary Slip</div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-3"><label class="form-label fw-semibold">Teacher <span class="text-danger">*</span></label>
                                    <select name="teacher_id" class="form-select" required>
                                        <option value="">Select Teacher</option>
                                        <?php foreach ($teachers as $t): ?>
                                            <option value="<?= $t['teacher_id'] ?>"><?= htmlspecialchars($t['full_name']) ?> (<?= htmlspecialchars($t['employee_id']) ?>)<?= $t['salary'] ? ' - Rs.' . number_format($t['salary'], 0) : '' ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3"><label class="form-label fw-semibold">Month <span class="text-danger">*</span></label>
                                    <input type="month" name="slip_month" class="form-control" value="<?= date('Y-m') ?>" required>
                                </div>
                                <div class="mb-3"><label class="form-label fw-semibold">Basic Salary</label>
                                    <input type="number" name="basic_salary" class="form-control" step="0.01" min="0" placeholder="Basic salary" required>
                                </div>
                                <div class="mb-3"><label class="form-label fw-semibold">Allowances</label>
                                    <input type="number" name="allowances" class="form-control" step="0.01" min="0" value="0">
                                </div>
                                <div class="mb-3"><label class="form-label fw-semibold">Deductions</label>
                                    <input type="number" name="deductions" class="form-control" step="0.01" min="0" value="0">
                                </div>
                                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-file-earmark-plus me-1"></i>Generate Slip</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="card mb-3">
                        <div class="card-header p-3"><i class="bi bi-funnel text-success me-2"></i>Filter</div>
                        <div class="card-body py-2">
                            <form method="GET" class="row g-2">
                                <div class="col-md-6">
                                    <select name="month" class="form-select" onchange="this.form.submit()">
                                        <option value="">All Months</option>
                                        <?php foreach ($months as $m): ?><option value="<?= $m ?>" <?= $month_filter == $m ? 'selected' : '' ?>><?= date('F Y', strtotime($m . '-01')) ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header p-3 d-flex justify-content-between align-items-center"><span><i class="bi bi-wallet2 text-warning me-2"></i>Generated Slips</span><span class="badge bg-primary"><?= count($slips) ?></span></div>
                        <div class="card-body">
                            <?php if (empty($slips)): ?>
                                <div class="empty-state"><i class="bi bi-file-earmark-x"></i><p class="mb-0">No salary slips generated yet.</p></div>
                            <?php else: ?>
                                <?php foreach ($slips as $slip): ?>
                                    <div class="slip-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <strong><?= htmlspecialchars($slip['full_name']) ?></strong>
                                                <span class="badge bg-primary ms-2"><?= date('F Y', strtotime($slip['slip_month'] . '-01')) ?></span>
                                                <div class="small text-muted mt-1"><i class="bi bi-person-badge me-1"></i><?= htmlspecialchars($slip['employee_id']) ?></div>
                                            </div>
                                            <div class="text-end">
                                                <div class="fw-bold text-success">Rs. <?= number_format($slip['net_salary'], 2) ?></div>
                                                <small class="text-muted">Net Salary</small>
                                            </div>
                                        </div>
                                        <div class="row mt-2 small border-top pt-2">
                                            <div class="col-4">Basic: <strong>Rs. <?= number_format($slip['basic_salary'], 2) ?></strong></div>
                                            <div class="col-4">Allowances: <strong>Rs. <?= number_format($slip['allowances'], 2) ?></strong></div>
                                            <div class="col-4">Deductions: <strong class="text-danger">Rs. <?= number_format($slip['deductions'], 2) ?></strong></div>
                                        </div>
                                        <div class="d-flex gap-2 mt-2">
                                            <a href="salary_slip_print.php?id=<?= $slip['id'] ?>" class="btn btn-sm btn-outline-primary" target="_blank"><i class="bi bi-printer me-1"></i>Print</a>
                                            <a href="salary_slips.php?delete_id=<?= $slip['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this slip?')"><i class="bi bi-trash"></i></a>
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
