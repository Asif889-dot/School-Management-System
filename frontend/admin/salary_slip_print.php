<?php
// Admin - Print Salary Slip
require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();
requireRole('admin');

$db = $pdo;
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$slip = null;
if ($id > 0) {
    $stmt = $db->prepare("SELECT ss.*, t.employee_id, t.qualification, u.full_name, u.email
                          FROM salary_slips ss
                          JOIN teachers t ON ss.teacher_id = t.teacher_id
                          JOIN users u ON t.user_id = u.id
                          WHERE ss.id = ?");
    $stmt->execute([$id]);
    $slip = $stmt->fetch();
}
if (!$slip) { die('Salary slip not found.'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Salary Slip - <?= htmlspecialchars($slip['full_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f2f5; }
        .page { max-width: 800px; margin: 20px auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { border-bottom: 3px solid #1a3a5c; padding-bottom: 15px; margin-bottom: 20px; text-align: center; }
        .header h2 { color: #1a3a5c; margin: 0; }
        .slip-meta { display: flex; justify-content: space-between; margin: 15px 0; }
        .table td, .table th { padding: 10px; }
        .total-row { background: #f8f9fa; font-weight: bold; }
        .net-row { background: #1a3a5c; color: white; font-weight: bold; }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <h2>Smart Choice Academy</h2>
            <div>Salary Slip</div>
        </div>
        <div class="slip-meta">
            <div><strong>Employee:</strong> <?= htmlspecialchars($slip['full_name']) ?><br><strong>Employee ID:</strong> <?= htmlspecialchars($slip['employee_id']) ?></div>
            <div class="text-end"><strong>Month:</strong> <?= date('F Y', strtotime($slip['slip_month'] . '-01')) ?><br><strong>Generated:</strong> <?= date('d M Y', strtotime($slip['generated_at'])) ?></div>
        </div>
        <table class="table table-bordered">
            <tr><td>Basic Salary</td><td class="text-end">Rs. <?= number_format($slip['basic_salary'], 2) ?></td></tr>
            <tr><td>Allowances</td><td class="text-end">Rs. <?= number_format($slip['allowances'], 2) ?></td></tr>
            <tr><td>Deductions</td><td class="text-end">Rs. <?= number_format($slip['deductions'], 2) ?></td></tr>
            <tr class="net-row"><td>Net Salary</td><td class="text-end">Rs. <?= number_format($slip['net_salary'], 2) ?></td></tr>
        </table>
        <div class="mt-4 d-flex justify-content-between">
            <div>_____________________<br>Employee Signature</div>
            <div>_____________________<br>Authorized Signature</div>
        </div>
        <div class="text-center mt-4"><button class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer"></i> Print</button></div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css"></script>
</body>
</html>
