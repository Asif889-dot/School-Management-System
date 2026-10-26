<?php
require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();

$success_msg = "";
$error_msg = "";
$account = null;

if (isset($_GET['id']) && !empty(trim($_GET['id']))) {
    $id = intval($_GET['id']);
    try {
        $stmt = $pdo->prepare("SELECT * FROM accounts WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $account = $stmt->fetch();
        if (!$account) { $error_msg = "Account entity target could not be recovered."; }
    } catch (PDOException $e) { $error_msg = "Database Error: " . $e->getMessage(); }
} elseif ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: account.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $account_id   = intval($_POST['account_id'] ?? 0);
    $account_name = trim($_POST['account_name'] ?? '');
    $account_code = trim($_POST['account_code'] ?? '');
    $account_type = trim($_POST['account_type'] ?? '');
    $status       = trim($_POST['status'] ?? 'active');

    if (empty($account_name) || empty($account_code) || empty($account_type)) {
        $error_msg = "All required form fields must be completed cleanly.";
    } else {
        try {
            // Guarantee editing code does not clash with existing rows
            $check = $pdo->prepare("SELECT id FROM accounts WHERE account_code = :code AND id != :id");
            $check->execute([':code' => $account_code, ':id' => $account_id]);
            
            if ($check->fetch()) {
                $error_msg = "The ledger code '<strong>$account_code</strong>' is already assigned to a separate index.";
            } else {
                $update = $pdo->prepare("UPDATE accounts SET account_name = :name, account_code = :code, account_type = :type, status = :status WHERE id = :id");
                $update->execute([
                    ':name'   => $account_name,
                    ':code'   => $account_code,
                    ':type'   => $account_type,
                    ':status' => $status,
                    ':id'     => $account_id
                ]);

                $success_msg = "Accounting modifications saved successfully!";
                
                // Keep UI synced
                $account['account_name'] = $account_name;
                $account['account_code'] = $account_code;
                $account['account_type'] = $account_type;
                $account['status']       = $status;
            }
        } catch (PDOException $e) { $error_msg = "Execution Matrix Dropped: " . $e->getMessage(); }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Account Head | Smart Choice Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <style>
        body { background-color: #f0f2f5; font-family: 'Inter', sans-serif; display: flex; }
        .main-content { margin-left: 260px; padding: 30px; width: 100%; }
        .card { border: none; border-radius: 15px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); }
        .form-label { font-weight: 600; font-size: 0.85rem; color: #495057; }
        .btn-update { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; color: white; font-weight: 600; }
    </style>
</head>
<body>
    <?php include '../../backend/sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid py-4">
            <div class="row align-items-center mb-4">
                <div class="col-md-8">
                    <h2 class="fw-bold text-dark mb-1">Modify Account Entry</h2>
                    <p class="text-muted">Adjust system accounting metrics or balance layer references safely.</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <a href="account.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back to Chart</a>
                </div>
            </div>

            <?php if (!empty($success_msg)): ?>
                <div class="alert alert-success alert-dismissible fade show card p-3 mb-4 border-start border-5 border-success" role="alert">
                    <div class="d-flex align-items-center"><i class="bi bi-patch-check-fill fs-4 me-3 text-success"></i><div><?= $success_msg ?></div></div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger alert-dismissible fade show card p-3 mb-4 border-start border-5 border-danger" role="alert">
                    <div class="d-flex align-items-center"><i class="bi bi-exclamation-triangle-fill fs-4 me-3 text-danger"></i><div><?= $error_msg ?></div></div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($account): ?>
                <div class="card p-4 p-md-5 bg-white">
                    <form action="<?= htmlspecialchars($_SERVER['PHP_SELF'] . '?id=' . $account['id']) ?>" method="POST">
                        <input type="hidden" name="account_id" value="<?= $account['id'] ?>">

                        <div class="row g-4">
                            <div class="col-md-8">
                                <label class="form-label text-uppercase">Account Title Descriptor</label>
                                <input type="text" name="account_name" class="form-control bg-light" value="<?= htmlspecialchars($account['account_name']) ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-uppercase">System Account Code</label>
                                <input type="text" name="account_code" class="form-control bg-light" value="<?= htmlspecialchars($account['account_code']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-uppercase">Accounting Classification Matrix</label>
                                <select name="account_type" class="form-select bg-light" required>
                                    <option value="Asset" <?= $account['account_type'] === 'Asset' ? 'selected' : '' ?>>Asset</option>
                                    <option value="Liability" <?= $account['account_type'] === 'Liability' ? 'selected' : '' ?>>Liability</option>
                                    <option value="Equity" <?= $account['account_type'] === 'Equity' ? 'selected' : '' ?>>Equity</option>
                                    <option value="Revenue" <?= $account['account_type'] === 'Revenue' ? 'selected' : '' ?>>Revenue</option>
                                    <option value="Expense" <?= $account['account_type'] === 'Expense' ? 'selected' : '' ?>>Expense</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-uppercase">Availability Node State</label>
                                <select name="status" class="form-select bg-light">
                                    <option value="active" <?= $account['status'] === 'active' ? 'selected' : '' ?>>Active / Postable Ledger</option>
                                    <option value="disabled" <?= $account['status'] === 'disabled' ? 'selected' : '' ?>>Disabled / Locked Ledger</option>
                                </select>
                            </div>
                            <div class="col-12 mt-5 text-end">
                                <a href="accounts.php" class="btn btn-light px-4 me-2">Cancel</a>
                                <button type="submit" class="btn btn-update px-5 py-2 shadow-sm">Save Adjustments</button>
                            </div>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>