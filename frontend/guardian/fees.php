<?php
// Guardian - View Child Fee Records

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

// Fetch fee structure for selected student's class
$fee_structure = [];
if ($selected_student > 0) {
    // Get student's class & academic year
    $stmt = $db->prepare("SELECT class_id, academic_year_id FROM students WHERE id = ?");
    $stmt->execute([$selected_student]);
    $student_fee = $stmt->fetch();

    if ($student_fee) {
        try {
            $stmt = $db->prepare("
                SELECT fs.*, ft.name as fee_name, ft.description
                FROM fee_structures fs
                JOIN fee_types ft ON fs.fee_type_id = ft.id
                WHERE fs.class_id = ? AND fs.academic_year_id = ?
                ORDER BY ft.name
            ");
            $stmt->execute([$student_fee['class_id'], $student_fee['academic_year_id']]);
            $fee_structure = $stmt->fetchAll();
        } catch (PDOException $e) {
            $fee_structure = [];
        }
    }
}

// Fetch fee payments for selected student
$payments = [];
$total_paid = 0;
if ($selected_student > 0) {
    try {
        $stmt = $db->prepare("
            SELECT fp.*, ft.name as fee_name
            FROM fee_payments fp
            LEFT JOIN fee_structures fs ON fp.fee_structure_id = fs.id
            LEFT JOIN fee_types ft ON fs.fee_type_id = ft.id
            WHERE fp.student_id = ?
            ORDER BY fp.payment_date DESC
        ");
        $stmt->execute([$selected_student]);
        $payments = $stmt->fetchAll();
        foreach ($payments as $p) {
            if ($p['status'] == 'paid') $total_paid += $p['amount_paid'];
        }
    } catch (PDOException $e) {
        $payments = [];
    }
}

// Total expected (sum of fee structure)
$total_expected = 0;
foreach ($fee_structure as $f) $total_expected += $f['amount'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Records - Smart Choice Academy</title>
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
        .table-card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .table-card th { background: #f8f9fa; padding: 12px; font-size: 12px; font-weight: 700; text-transform: uppercase; }
        .table-card td { padding: 10px 12px; font-size: 13px; vertical-align: middle; }
        .fee-card { background: white; border-radius: 12px; padding: 15px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .fee-number { font-size: 24px; font-weight: 700; }
        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .paid { background: #d4edda; color: #155724; }
        .pending { background: #fff3cd; color: #856404; }
        .cancelled { background: #f8d7da; color: #721c24; }
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
                        <h4 class="fw-bold mb-1"><i class="bi bi-cash-coin me-2"></i>Fee Records</h4>
                        <p class="mb-0 opacity-75"><?= htmlspecialchars($guardian_name) ?> | View child fee details</p>
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
                                        <?= htmlspecialchars($child['first_name'] . ' ' . $child['last_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>

                <div class="row mb-4">
                    <div class="col-md-4 mb-2"><div class="fee-card"><div class="fee-number text-primary">Rs. <?= number_format($total_expected, 2) ?></div><div class="small text-muted">Total Fee Structure</div></div></div>
                    <div class="col-md-4 mb-2"><div class="fee-card"><div class="fee-number text-success">Rs. <?= number_format($total_paid, 2) ?></div><div class="small text-muted">Total Paid</div></div></div>
                    <div class="col-md-4 mb-2"><div class="fee-card"><div class="fee-number text-danger">Rs. <?= number_format(max(0, $total_expected - $total_paid), 2) ?></div><div class="small text-muted">Balance</div></div></div>
                </div>

                <div class="table-card mb-4">
                    <div class="card-header p-3 bg-white border-bottom fw-semibold"><i class="bi bi-list-columns me-2"></i>Fee Structure</div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Fee Type</th><th>Frequency</th><th>Due Date</th><th class="text-end">Amount</th></tr></thead>
                            <tbody>
                                <?php if (empty($fee_structure)): ?>
                                    <tr><td colspan="4" class="text-center py-4 text-muted">No fee structure configured for this class.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($fee_structure as $fee): ?>
                                        <tr>
                                            <td class="fw-semibold"><?= htmlspecialchars($fee['fee_name']) ?></td>
                                            <td><?= ucfirst($fee['frequency']) ?></td>
                                            <td><?= !empty($fee['due_date']) ? date('d M Y', strtotime($fee['due_date'])) : '—' ?></td>
                                            <td class="text-end">Rs. <?= number_format($fee['amount'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="table-card">
                    <div class="card-header p-3 bg-white border-bottom fw-semibold"><i class="bi bi-receipt me-2"></i>Payment History</div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Receipt#</th><th>Fee</th><th>Date</th><th>Method</th><th class="text-end">Amount</th><th>Status</th></tr></thead>
                            <tbody>
                                <?php if (empty($payments)): ?>
                                    <tr><td colspan="6" class="text-center py-4 text-muted">No payments recorded yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($payments as $p): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($p['receipt_no']) ?></td>
                                            <td><?= htmlspecialchars($p['fee_name'] ?? 'Fee') ?></td>
                                            <td><?= date('d M Y', strtotime($p['payment_date'])) ?></td>
                                            <td><?= $p['payment_method'] ?></td>
                                            <td class="text-end">Rs. <?= number_format($p['amount_paid'], 2) ?></td>
                                            <td><span class="status-badge <?= $p['status'] ?>"><?= ucfirst($p['status']) ?></span></td>
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
