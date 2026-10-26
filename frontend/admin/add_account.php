<?php
require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();

$success_msg = "";
$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $account_name = trim($_POST['account_name'] ?? '');
    $account_code = trim($_POST['account_code'] ?? '');
    $account_type = trim($_POST['account_type'] ?? '');
    $status       = trim($_POST['status'] ?? 'active');

    if (empty($account_name) || empty($account_code) || empty($account_type)) {
        $error_msg = "All fields marked with an asterisk (*) are strictly required.";
    } else {
        try {
            // Verify if account code isn't violating uniqueness constraints
            $check = $pdo->prepare("SELECT id FROM accounts WHERE account_code = :code");
            $check->execute([':code' => $account_code]);
            
            if ($check->fetch()) {
                $error_msg = "The account code '<strong>$account_code</strong>' is already allocated to another ledger head.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO accounts (account_name, account_code, account_type, status, created_at) 
                                       VALUES (:name, :code, :type, :status, NOW())");
                $stmt->execute([
                    ':name'   => $account_name,
                    ':code'   => $account_code,
                    ':type'   => $account_type,
                    ':status' => $status
                ]);

                $success_msg = "Account head '<strong>" . htmlspecialchars($account_name) . "</strong>' registered safely!";
                $account_name = $account_code = $account_type = ""; // Flush variables
            }
        } catch (PDOException $e) {
            $error_msg = "Ledger Injection Defect: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Account Head | Smart Choice Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <style>
        body { background-color: #f0f2f5; font-family: 'Inter', sans-serif; display: flex; }
        .main-content { margin-left: 260px; padding: 30px; width: 100%; }
        .card { border: none; border-radius: 15px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); }
        .form-label { font-weight: 600; font-size: 0.85rem; color: #495057; letter-spacing: 0.5px; }
        .btn-submit { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; color: white; font-weight: 600; }
        .btn-submit:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(118,75,162,0.25); color: white; }
    </style>
</head>
<body>
    <?php include '../../backend/sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid py-4">
            <div class="row align-items-center mb-4">
                <div class="col-md-8">
                    <h2 class="fw-bold text-dark mb-1">Add Account Head</h2>
                    <p class="text-muted">Incorporate customized ledger fields into your organizational Chart of Accounts matrix.</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <a href="account.php" class="btn btn-outline-secondary"><i class="bi bi-list-ul me-1"></i> Account Index</a>
                </div>
            </div>

            <?php if (!empty($success_msg)): ?>
                <div class="alert alert-success alert-dismissible fade show card p-3 mb-4 border-start border-5 border-success" role="alert">
                    <div class="d-flex align-items-center"><i class="bi bi-check-circle-fill fs-4 me-3 text-success"></i><div><?= $success_msg ?></div></div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger alert-dismissible fade show card p-3 mb-4 border-start border-5 border-danger" role="alert">
                    <div class="d-flex align-items-center"><i class="bi bi-exclamation-triangle-fill fs-4 me-3 text-danger"></i><div><?= $error_msg ?></div></div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card p-4 p-md-5 bg-white">
                <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST" autocomplete="off">
                    <div class="row g-4">
                        <div class="col-md-8">
                            <label class="form-label text-uppercase">Account Head Title <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-tag"></i></span>
                                <input type="text" name="account_name" class="form-control bg-light" placeholder="e.g., Tuition Fee Revenue" value="<?= htmlspecialchars($account_name ?? '') ?>" required>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-uppercase">Account Code (GL Code) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-hash"></i></span>
                                <input type="text" name="account_code" class="form-control bg-light" placeholder="e.g., REV-4001" value="<?= htmlspecialchars($account_code ?? '') ?>" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-uppercase">Accounting Classification <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-diagram-2"></i></span>
                                <select name="account_type" class="form-select bg-light" required>
                                    <option value="">Select Structure Category...</option>
                                    <option value="Asset" <?= isset($account_type) && $account_type === 'Asset' ? 'selected' : '' ?>>Asset (Receivables, Inventory, Cash)</option>
                                    <option value="Liability" <?= isset($account_type) && $account_type === 'Liability' ? 'selected' : '' ?>>Liability (Payables, Loans)</option>
                                    <option value="Equity" <?= isset($account_type) && $account_type === 'Equity' ? 'selected' : '' ?>>Equity (Capital, Retained Earnings)</option>
                                    <option value="Revenue" <?= isset($account_type) && $account_type === 'Revenue' ? 'selected' : '' ?>>Revenue (Incomes, Fees, Sales)</option>
                                    <option value="Expense" <?= isset($account_type) && $account_type === 'Expense' ? 'selected' : '' ?>>Expense (Payroll, Utilities, Operations)</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-uppercase">Operational Availability Status</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-toggle-on"></i></span>
                                <select name="status" class="form-select bg-light">
                                    <option value="active">Active / Postable Ledger</option>
                                    <option value="disabled">Disabled / Locked Ledger</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-12 mt-5 text-end">
                            <button type="reset" class="btn btn-light px-4 me-2">Clear</button>
                            <button type="submit" class="btn btn-submit px-5 py-2 shadow-sm">Save Financial Head</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>