<?php
require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();

$error_msg = "";
$search_query = "";
$params = [];

// Handle Search Query Filter Matrix
if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search_query = trim($_GET['search']);
    $sql = "SELECT id, account_name, account_code, account_type, status, created_at 
            FROM accounts 
            WHERE account_name LIKE :search OR account_code LIKE :search OR account_type LIKE :search
            ORDER BY account_type ASC, account_name ASC";
    $params = [':search' => '%' . $search_query . '%'];
} else {
    $sql = "SELECT id, account_name, account_code, account_type, status, created_at 
            FROM accounts 
            ORDER BY account_type ASC, account_name ASC";
}

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $accounts = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_msg = "Account Ledger Retrieval Failure: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Chart of Accounts | Smart Choice Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <style>
        body { background-color: #f0f2f5; font-family: 'Inter', sans-serif; display: flex; }
        .main-content { margin-left: 260px; padding: 30px; width: 100%; }
        .card { border: none; border-radius: 15px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); }
        .table-responsive { border-radius: 12px; overflow: hidden; }
        .table thead { background-color: #f8f9fa; }
        .table th { font-weight: 600; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; color: #495057; padding: 15px; }
        .table td { padding: 15px; vertical-align: middle; }
        .badge-type { background-color: #e0f2fe; color: #0369a1; font-weight: 600; font-size: 0.85rem; padding: 6px 12px; border-radius: 8px; }
        .badge-code { background-color: #f3f4f6; color: #374151; font-weight: 500; font-size: 0.8rem; padding: 4px 8px; border-radius: 6px; font-family: monospace; }
        .btn-search { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; }
        .btn-search:hover { background: linear-gradient(135deg, #5a73e8 0%, #6c3fa1 100%); color: white; }
    </style>
</head>

<body>

    <?php include '../../backend/sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid py-4">

            <!-- Top Header Panel -->
            <div class="row align-items-center mb-4">
                <div class="col-md-8">
                    <h2 class="fw-bold text-dark mb-1">Chart of Accounts</h2>
                    <p class="text-muted">Manage system account categories, accounting head indicators, and dynamic transactional nodes.</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <a href="add_account.php" class="btn btn-primary px-4 py-2 shadow-sm border-0" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <i class="bi bi-plus-circle me-2"></i> Add Account Head
                    </a>
                </div>
            </div>

            <!-- Status Alert Messages -->
            <?php if (isset($_GET['status']) && $_GET['status'] === 'deleted'): ?>
                <div class="alert alert-success alert-dismissible fade show card p-3 mb-4 border-start border-5 border-success" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-trash3-fill fs-4 me-3 text-success"></i>
                        <div>The selected account head profile has been dropped from the system registry.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger card p-3 mb-4 border-start border-5 border-danger" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle-fill fs-4 me-3 text-danger"></i>
                        <div><?= $error_msg ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Filter Section -->
            <div class="card p-4 mb-4 bg-white">
                <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="GET" class="row g-3">
                    <div class="col-md-9">
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control bg-light" placeholder="Search accounts by name, code mapping, or ledger type..." value="<?= htmlspecialchars($search_query) ?>">
                        </div>
                    </div>
                    <div class="col-md-3 d-grid">
                        <button type="submit" class="btn btn-search py-2 fw-semibold">Filter Directory</button>
                    </div>
                </form>
            </div>

            <!-- Main Data Table Container -->
            <div class="card bg-white p-0 overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width: 80px;" class="text-center">SR #</th>
                                <th>Account Title</th>
                                <th>Systematic Account Code</th>
                                <th>Account Type Category</th>
                                <th class="text-center">Status</th>
                                <th style="width: 150px;" class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($accounts)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="bi bi-bank fs-1 d-block mb-3 text-secondary"></i>
                                        No financial chart records found matching your parameter criteria.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php $sr = 1; foreach ($accounts as $acc): ?>
                                    <tr>
                                        <td class="text-center fw-bold text-secondary"><?= $sr++ ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="p-2 bg-light text-primary rounded-3 me-3">
                                                    <i class="bi bi-wallet2 fs-5"></i>
                                                </div>
                                                <div>
                                                    <span class="fw-bold text-dark d-block"><?= htmlspecialchars($acc['account_name']) ?></span>
                                                    <small class="text-muted" style="font-size: 0.75rem;">Created: <?= date('d-M-Y', strtotime($acc['created_at'])) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="badge-code"><?= htmlspecialchars($acc['account_code']) ?></span></td>
                                        <td><span class="badge-type"><?= htmlspecialchars($acc['account_type']) ?></span></td>
                                        <td class="text-center">
                                            <?php if ($acc['status'] === 'active'): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3">Disabled</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group" role="group">
                                                <a href="edit_account.php?id=<?= $acc['id'] ?>" class="btn btn-sm btn-outline-primary border-0 rounded-2" title="Edit Configuration"><i class="bi bi-pencil-square fs-5"></i></a>
                                                <a href="delete_account.php?id=<?= $acc['id'] ?>" class="btn btn-sm btn-outline-danger border-0 rounded-2" onclick="return confirm('Are you sure you want to drop this ledger configuration entry?')" title="Delete Account"><i class="bi bi-trash3 fs-5"></i></a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>