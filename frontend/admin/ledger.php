<?php
require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();
$error_msg = "";
try {
    $query = "SELECT t.voucher_no, t.transaction_date, t.reference, a.account_name, a.account_code, d.debit, d.credit, d.description 
              FROM transaction_details d
              JOIN transactions t ON d.transaction_id = t.id
              JOIN accounts a ON d.account_id = a.id
              ORDER BY t.transaction_date DESC, t.id DESC";
    $ledger_stmt = $pdo->query($query);
    $records = $ledger_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_msg = "Ledger Extraction Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>General Ledger Audit Log - SCA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <style>
        body {
            background: #f8f9fa;
        }

        .main-content {
            margin-left: 260px;
            padding: 30px;
        }
    </style>
</head>

<body>
    <?php include '../../backend/sidebar.php'; ?>
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold text-dark text-uppercase">General Ledger Log</h3>
                <small class="text-muted">Consolidated historical balancing trail statement overview logs</small>
            </div>
            <a href="add_transaction.php" class="btn btn-outline-dark"><i class="bi bi-journal-plus me-1"></i> New Entry</a>
        </div>

        <?php if ($error_msg): ?><div class="alert alert-danger"><?= $error_msg ?></div><?php endif; ?>

        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <table class="table table-bordered table-hover mb-0 align-middle">
                    <thead class="table-secondary text-dark">
                        <tr>
                            <th>Date</th>
                            <th>Voucher / Token Reference</th>
                            <th>Target Head</th>
                            <th>Description</th>
                            <th class="text-end">Debit (PKR)</th>
                            <th class="text-end">Credit (PKR)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($records)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">No historical transactions mapped to ledger accounts.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($records as $r): ?>
                                <tr>
                                    <td><?= htmlspecialchars($r['transaction_date']) ?></td>
                                    <td>
                                        <span class="badge bg-dark font-monospace"><?= htmlspecialchars($r['voucher_no']) ?></span>
                                        <?php if ($r['reference']): ?><br><small class="text-muted">Ref: #<?= htmlspecialchars($r['reference']) ?></small><?php endif; ?>
                                    </td>
                                    <td>
                                        <strong class="text-secondary">[<?= $r['account_code'] ?>]</strong><br>
                                        <span class="text-dark small fw-bold"><?= htmlspecialchars($r['account_name']) ?></span>
                                    </td>
                                    <td class="small text-muted"><?= htmlspecialchars($r['description']) ?></td>
                                    <td class="text-end text-success fw-semibold"><?= ($r['debit'] > 0) ? number_format($r['debit'], 2) : '-' ?></td>
                                    <td class="text-end text-danger fw-semibold"><?= ($r['credit'] > 0) ? number_format($r['credit'], 2) : '-' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>

</html>