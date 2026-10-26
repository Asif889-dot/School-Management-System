<?php
require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();
requireRole('admin'); // Security gate enforcement

$success_msg = "";
$error_msg = "";

// Handle Operational Actions (POST Workflow Matrix)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // ACTION A: CREATE NEW TRIGGER NODE BLOCK
    if (isset($_POST['action_type']) && $_POST['action_type'] === 'create_trigger') {
        $new_trigger    = strtoupper(trim($_POST['module_trigger'] ?? ''));
        $description    = trim($_POST['description'] ?? '');
        $init_account_id = !empty($_POST['initial_account_id']) ? intval($_POST['initial_account_id']) : null;

        // Strip characters to keep clean standard snake_case constants
        $new_trigger = preg_replace('/[^A-Z0-9_]/', '', str_replace(' ', '_', $new_trigger));

        if (empty($new_trigger) || empty($description)) {
            $error_msg = "All core trigger configuration parameters are required.";
        } else {
            try {
                // Verify uniqueness constraints before attempting compilation
                $checkStmt = $pdo->prepare("SELECT id FROM account_mappings WHERE module_trigger = ?");
                $checkStmt->execute([$new_trigger]);
                
                if ($checkStmt->fetch()) {
                    $error_msg = "The operational trigger key code matrix [{$new_trigger}] already exists.";
                } else {
                    // REMOVED created_at / updated_at from manual INSERT to match your exact schema fields
                    $insertStmt = $pdo->prepare("
                        INSERT INTO account_mappings (module_trigger, description, account_id) 
                        VALUES (:module_trigger, :description, :account_id)
                    ");
                    $insertStmt->execute([
                        ':module_trigger' => $new_trigger,
                        ':description'    => $description,
                        ':account_id'     => $init_account_id
                    ]);
                    $success_msg = "New functional core mapping trigger node successfully deployed to system ledger pipeline.";
                }
            } catch (PDOException $e) {
                $error_msg = "Failed to compile trigger schema entry: " . $e->getMessage();
            }
        }
    }
    
    // ACTION B: UPDATE EXISTING RELATIONAL MATRIX SYNC
    elseif (isset($_POST['action_type']) && $_POST['action_type'] === 'update_trigger') {
        $map_id     = intval($_POST['map_id'] ?? 0);
        $account_id = !empty($_POST['account_id']) ? intval($_POST['account_id']) : null;

        if (empty($map_id) || empty($account_id)) {
            $error_msg = "Invalid ledger relational matrix elements assigned.";
        } else {
            try {
                // Relies cleanly on MySQL's internal ON UPDATE CURRENT_TIMESTAMP definition
                $stmt = $pdo->prepare("UPDATE account_mappings SET account_id = :account_id WHERE id = :id");
                $stmt->execute([':account_id' => $account_id, ':id' => $map_id]);
                $success_msg = "Functional system accounting transaction target updated cleanly.";
            } catch (PDOException $e) {
                $error_msg = "Database Entity Linkage Failed: " . $e->getMessage();
            }
        }
    }

    // ACTION C: DELETE OPERATIONAL TRIGGER NODE
    elseif (isset($_POST['action_type']) && $_POST['action_type'] === 'delete_trigger') {
        $map_id = intval($_POST['map_id'] ?? 0);

        if (empty($map_id)) {
            $error_msg = "Invalid trigger deletion reference parameter identifier.";
        } else {
            try {
                $deleteStmt = $pdo->prepare("DELETE FROM account_mappings WHERE id = ?");
                $deleteStmt->execute([$map_id]);
                $success_msg = "Functional operation trigger node completely dropped from system ledger routing arrays.";
            } catch (PDOException $e) {
                $error_msg = "Failed to delete trigger node context entry: " . $e->getMessage();
            }
        }
    }
}

// Extract Mapping Structure Grid Elements 
try {
    $mappings = $pdo->query("SELECT am.id, am.module_trigger, am.description, am.account_id, acc.account_name, acc.account_code 
                             FROM account_mappings am 
                             LEFT JOIN accounts acc ON am.account_id = acc.id 
                             ORDER BY am.module_trigger ASC")->fetchAll(PDO::FETCH_ASSOC);

    $accounts = $pdo->query("SELECT id, account_name, account_code FROM accounts WHERE status = 'active' ORDER BY account_name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_msg = "Structural Mapping Matrices Extract Fault: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Ledger Mapping | Smart Choice Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <style>
        body { background-color: #f0f2f5; font-family: 'Inter', sans-serif; display: flex; }
        .main-content { margin-left: 260px; padding: 30px; width: 100%; min-height: 100vh; }
        .card { border: none; border-radius: 15px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); }
        .form-select-sm { border-radius: 8px; font-size: 0.85rem; padding: 6px 12px; }
        .module-key { font-family: monospace; font-weight: 700; color: #4f46e5; background-color: #f5f3ff; padding: 4px 10px; border-radius: 6px; text-transform: uppercase; }
        .btn-save-map { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border: none; }
        .btn-create-node { background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%); color: white; border: none; border-radius: 8px; font-weight: 500; }
        .btn-create-node:hover { color: white; opacity: 0.9; }
        .modal-content { border-radius: 16px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
    </style>
</head>

<body>

    <?php include '../../backend/sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid py-2">

            <div class="row align-items-center mb-4">
                <div class="col-sm-8">
                    <h2 class="fw-bold text-dark mb-1">System Account Mapping</h2>
                    <p class="text-muted mb-0">Route dynamic system operations (Fees, Payroll, Bookstore Sales) directly to specific ledger destination paths.</p>
                </div>
                <div class="col-sm-4 text-sm-end mt-3 mt-sm-0">
                    <button type="button" class="btn btn-create-node px-4 py-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#newTriggerModal">
                        <i class="bi bi-plus-circle me-2"></i>Create Functional Trigger
                    </button>
                </div>
            </div>

            <?php if (!empty($success_msg)): ?>
                <div class="alert alert-success alert-dismissible fade show card p-3 mb-4 border-start border-5 border-success" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-shield-check fs-4 me-3 text-success"></i>
                        <div><?= htmlspecialchars($success_msg) ?></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger card p-3 mb-4 border-start border-5 border-danger" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-bug fs-4 me-3 text-danger"></i>
                        <div><?= htmlspecialchars($error_msg) ?></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card bg-white p-0 overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th style="width: 280px; padding: 15px;">Functional Operational Trigger</th>
                                <th>Functional Description Context</th>
                                <th style="width: 360px;">Assigned Ledger Target Account Head</th>
                                <th style="width: 180px;" class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($mappings)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-5 text-muted">
                                        <i class="bi bi-diagram-3 fs-1 d-block mb-3 text-secondary"></i>
                                        No structural trigger maps configured inside the database infrastructure.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($mappings as $map): ?>
                                    <tr>
                                        <td style="padding: 15px;"><span class="module-key"><?= htmlspecialchars($map['module_trigger']) ?></span></td>
                                        <td class="text-muted small"><?= htmlspecialchars($map['description']) ?></td>
                                        
                                        <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST">
                                            <input type="hidden" name="action_type" value="update_trigger">
                                            <input type="hidden" name="map_id" value="<?= $map['id'] ?>">
                                            <td>
                                                <select name="account_id" class="form-select form-select-sm bg-light" required>
                                                    <option value="">Select Ledger Destination Account...</option>
                                                    <?php foreach ($accounts as $acc): ?>
                                                        <option value="<?= $acc['id'] ?>" <?= $map['account_id'] == $acc['id'] ? 'selected' : '' ?>>
                                                            [<?= htmlspecialchars($acc['account_code']) ?>] - <?= htmlspecialchars($acc['account_name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td class="text-center">
                                                <div class="d-flex align-items-center justify-content-center gap-2">
                                                    <button type="submit" class="btn btn-sm btn-save-map px-2.5 rounded-2 shadow-sm" title="Update Node Linkage">
                                                        <i class="bi bi-arrow-repeat me-1"></i> Sync
                                                    </button>
                                        </form>
                                                    <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST" onsubmit="return confirm('CRITICAL WARNING: Are you sure you want to drop the operational trigger module [<?= htmlspecialchars($map['module_trigger']) ?>]? Deletion can break automated accounting routing engines like your fee collection listeners.');">
                                                        <input type="hidden" name="action_type" value="delete_trigger">
                                                        <input type="hidden" name="map_id" value="<?= $map['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger px-2.5 rounded-2" title="Drop Module Trigger">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
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

    <div class="modal fade" id="newTriggerModal" tabindex="-1" aria-labelledby="newTriggerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-dark" id="newTriggerModalLabel"><i class="bi bi-gear-wide-connected me-2 text-indigo"></i>New Account Mapping Trigger</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST">
                    <div class="modal-body py-4">
                        <input type="hidden" name="action_type" value="create_trigger">
                        
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">Functional Operational Trigger CONSTANT</label>
                            <input type="text" name="module_trigger" class="form-control" placeholder="e.g., TRANSPORT_FEES, COURIER_EXPENSES" style="text-transform: uppercase; font-family: monospace; font-size: 0.9rem;" required>
                            <div class="form-text text-muted" style="font-size: 0.75rem;">Spaces will automatically convert into standardized snake_case formatting parameters.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">Functional Description Context</label>
                            <textarea name="description" class="form-control small" rows="3" placeholder="Provide a concise statement mapping out what structural runtime transaction logic initiates this automation trigger..." required></textarea>
                        </div>

                        <div class="mb-0">
                            <label class="form-label small fw-semibold text-secondary">Default Target Ledger Account (Optional)</label>
                            <select name="initial_account_id" class="form-select text-muted">
                                <option value="">Select Ledger Destination Account...</option>
                                <?php foreach ($accounts as $acc): ?>
                                    <option value="<?= $acc['id'] ?>">
                                        [<?= htmlspecialchars($acc['account_code']) ?>] - <?= htmlspecialchars($acc['account_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-light border px-3 rounded-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-create-node px-4 rounded-2 shadow-sm">
                            <i class="bi bi-hdd-network-fill me-1"></i> Provision Trigger
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>