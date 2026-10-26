<?php

/**
 * Smart Choice Academy - Add Transaction System
 * Core accounting module for processing manual journal/double-entry vouchers.
 */

// Start session and verify permissions
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure database connection and authentication utilities are available
// Adjust these paths according to your backend architecture
require_once '../../backend/auth.php';
require_once '../../backend/config.php';

// Authorization check (matching your sidebar's 'fee_view' or custom accounting level)
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && !in_array('fee_view', $_SESSION['permissions'] ?? []))) {
    header("Location: dashboard.php");
    exit;
}

$success_msg = "";
$error_msg = "";

// Fetch active accounts for the dropdown selectors
$accounts = [];
try {
    // Fetches account_id, account_name, and account_code from your Chart of Accounts table
    $stmt = $pdo->query("SELECT id, account_name, account_code, account_type FROM accounts WHERE status = 'active' ORDER BY account_type, account_name ASC");
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_msg = "Error fetching chart of accounts: " . $e->getMessage();
}

// Process Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_transaction'])) {
    $transaction_date = $_POST['transaction_date'] ?? date('Y-m-d');
    $debit_account    = filter_input(INPUT_POST, 'debit_account', FILTER_VALIDATE_INT);
    $credit_account   = filter_input(INPUT_POST, 'credit_account', FILTER_VALIDATE_INT);
    $amount           = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
    $reference        = trim($_POST['reference'] ?? '');
    $description      = trim($_POST['description'] ?? '');
    $created_by       = $_SESSION['user_id'] ?? 1; // Fallback to system default if session missing

    // Validation
    if (!$debit_account || !$credit_account || !$amount || $amount <= 0) {
        $error_msg = "Please fill in all mandatory fields with valid parameters.";
    } elseif ($debit_account === $credit_account) {
        $error_msg = "Debit and Credit accounts cannot be identical in a double-entry transaction.";
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Generate unique transaction tracking token/voucher reference code
            $voucher_no = "JV-" . strtoupper(uniqid());

            // 2. Insert master voucher log record
            $master_sql = "INSERT INTO transactions (voucher_no, transaction_date, reference, description, total_amount, created_by, created_at) 
                           VALUES (:voucher_no, :t_date, :ref, :descr, :amount, :user_id, NOW())";
            $master_stmt = $pdo->prepare($master_sql);
            $master_stmt->execute([
                ':voucher_no' => $voucher_no,
                ':t_date'     => $transaction_date,
                ':ref'        => $reference,
                ':descr'      => $description,
                ':amount'     => $amount,
                ':user_id'    => $created_by
            ]);

            $transaction_id = $pdo->lastInsertId();

            // 3. Insert Debit Entry Ledger Item
            $ledger_sql = "INSERT INTO transaction_details (transaction_id, account_id, debit, credit, description) 
                           VALUES (:t_id, :account_id, :debit, :credit, :descr)";
            $ledger_stmt = $pdo->prepare($ledger_sql);

            $ledger_stmt->execute([
                ':t_id'        => $transaction_id,
                ':account_id'  => $debit_account,
                ':debit'       => $amount,
                ':credit'      => 0.00,
                ':descr'       => "Debit: " . $description
            ]);

            // 4. Insert Credit Entry Ledger Item
            $ledger_stmt->execute([
                ':t_id'        => $transaction_id,
                ':account_id'  => $credit_account,
                ':debit'       => 0.00,
                ':credit'      => $amount,
                ':descr'       => "Credit: " . $description
            ]);

            $pdo->commit();
            $success_msg = "Transaction verified and posted successfully under Voucher: <strong>$voucher_no</strong>";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_msg = "Transaction failed to post. System error logs state: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Transaction - Smart Choice Academy</title>
    <!-- Framework Styles Sheets -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="../../assets/css/sidebar.css" />
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .main-content {
            margin-left: 260px;
            padding: 30px;
            transition: all 0.3s ease;
        }

        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .card-header {
            background-color: #fff;
            border-bottom: 1px solid #edf2f9;
            font-weight: 600;
            font-size: 1.15rem;
        }

        .form-label {
            font-weight: 500;
            color: #495057;
        }

        .mandatory:after {
            content: " *";
            color: #dc3545;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
        }
    </style>
</head>

<body>

    <!-- Include standard application sidebar context assembly -->
    <?php include '../../backend/sidebar.php'; ?>

    <!-- Context Wrapper Panel -->
    <div class="main-content">
        <div class="container-fluid">

            <!-- Context Layout Title Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-0 text-dark">Add Transaction</h3>
                    <small class="text-muted">Process safe double-entry ledger bookkeeping vouchers</small>
                </div>
                <a href="ledger.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> View Ledger Records
                </a>
            </div>

            <!-- Operational Notification Banners -->
            <?php if (!empty($success_msg)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i> <?= $success_msg ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $error_msg ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Core Operational Transaction Input Card -->
            <div class="card">
                <div class="card-header py-3">
                    <i class="bi bi-wallet2 me-2 text-primary"></i>Journal Entry Details
                </div>
                <div class="card-body p-4">
                    <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" id="transactionForm">

                        <div class="row mb-3">
                            <!-- Transaction Posting Date -->
                            <div class="col-md-4">
                                <label for="transaction_date" class="form-label mandatory">Posting Date</label>
                                <input type="date" class="form-control" id="transaction_date" name="transaction_date"
                                    value="<?= date('Y-m-d'); ?>" required>
                            </div>

                            <!-- Transaction Financial Value -->
                            <div class="col-md-4">
                                <label for="amount" class="form-label mandatory">Amount (PKR)</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rs.</span>
                                    <input type="number" step="0.01" min="0.01" class="form-control" id="amount" name="amount"
                                        placeholder="0.00" required>
                                </div>
                            </div>

                            <!-- Tracking Reference Code Document -->
                            <div class="col-md-4">
                                <label for="reference" class="form-label">Reference / Slip #</label>
                                <input type="text" class="form-control" id="reference" name="reference"
                                    placeholder="e.g., Check #, Bill Voucher #">
                            </div>
                        </div>

                        <hr class="my-4 text-muted opacity-25">

                        <div class="row mb-4">
                            <!-- Account Node receiving positive asset/expense weight -->
                            <div class="col-md-6">
                                <label for="debit_account" class="form-label mandatory">Debit Account (Receiving Account)</label>
                                <select class="form-select select-account" id="debit_account" name="debit_account" required>
                                    <option value="" selected disabled>-- Select Debit Target --</option>
                                    <?php foreach ($accounts as $acc): ?>
                                        <option value="<?= $acc['id'] ?>">
                                            <?= htmlspecialchars("[{$acc['account_code']}] {$acc['account_name']} ({$acc['account_type']})") ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text text-muted">Increases asset/expense accounts; decreases liability/equity/revenue channels.</div>
                            </div>

                            <!-- Account Node delivering asset value weight -->
                            <div class="col-md-6">
                                <label for="credit_account" class="form-label mandatory">Credit Account (Source Account)</label>
                                <select class="form-select select-account" id="credit_account" name="credit_account" required>
                                    <option value="" selected disabled>-- Select Credit Target --</option>
                                    <?php foreach ($accounts as $acc): ?>
                                        <option value="<?= $acc['id'] ?>">
                                            <?= htmlspecialchars("[{$acc['account_code']}] {$acc['account_name']} ({$acc['account_type']})") ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text text-muted">Decreases asset/expense accounts; increases liability/equity/revenue channels.</div>
                            </div>
                        </div>

                        <!-- Narrative / Description Block -->
                        <div class="mb-4">
                            <label for="description" class="form-label mandatory">Voucher Narration / Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"
                                placeholder="Provide an explicit narrative context for this specific double-entry balancing record..." required></textarea>
                        </div>

                        <!-- Active Action Dispatch Control Triggers -->
                        <div class="d-flex justify-content-end gap-2">
                            <button type="reset" class="btn btn-light px-4">Clear Form</button>
                            <button type="submit" name="submit_transaction" class="btn btn-primary px-5">
                                <i class="bi bi-check2-square me-1"></i> Post Transaction
                            </button>
                        </div>

                    </form>
                </div>
            </div>

        </div>
    </div>

    <!-- Client-Side Form Integrity Validation Engines -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('transactionForm').addEventListener('submit', function(e) {
            const debitAccount = document.getElementById('debit_account').value;
            const creditAccount = document.getElementById('credit_account').value;
            const amount = document.getElementById('amount').value;

            if (debitAccount === creditAccount) {
                e.preventDefault();
                alert('Accounting Integrity Error:\n\nYou cannot assign identical targets to both Debit and Credit fields in a double-entry balance voucher.');
                return false;
            }

            if (parseFloat(amount) <= 0 || isNaN(parseFloat(amount))) {
                e.preventDefault();
                alert('Input Processing Error:\n\nThe transaction monetary value must evaluate to a positive non-zero quantity.');
                return false;
            }
        });
    </script>
</body>

</html>