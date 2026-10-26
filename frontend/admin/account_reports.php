<?php

/**
 * Smart Choice Academy - Accounts Reporting Engine
 * Computes Trial Balance, Profit & Loss Statement, and Balance Sheets.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();

$selected_report = isset($_GET['report_type']) ? $_GET['report_type'] : 'trial_balance';
$as_of_date = isset($_GET['as_of_date']) ? $_GET['as_of_date'] : date('Y-m-d');

$error_msg = "";
$report_data = [];

try {
    switch ($selected_report) {
        case 'trial_balance':
            // Sum calculations aggregation for Trial Balance Matrix
            $sql = "SELECT a.account_code, a.account_name, a.account_type,
                           SUM(d.debit) as total_debit, SUM(d.credit) as total_credit
                    FROM accounts a
                    LEFT JOIN transaction_details d ON a.id = d.account_id
                    LEFT JOIN transactions t ON d.transaction_id = t.id AND t.transaction_date <= :as_of_date
                    WHERE a.status = 'active'
                    GROUP BY a.id
                    HAVING total_debit > 0 OR total_credit > 0
                    ORDER BY a.account_type ASC, a.account_code ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':as_of_date' => $as_of_date]);
            $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'profit_loss':
            // Isolate Revenues and Expenses to calculate net surplus margins
            $sql = "SELECT a.account_code, a.account_name, a.account_type,
                           SUM(d.debit) as total_debit, SUM(d.credit) as total_credit
                    FROM accounts a
                    LEFT JOIN transaction_details d ON a.id = d.account_id
                    LEFT JOIN transactions t ON d.transaction_id = t.id AND t.transaction_date <= :as_of_date
                    WHERE a.status = 'active' AND a.account_type IN ('Revenue', 'Expense')
                    GROUP BY a.id
                    ORDER BY a.account_type ASC, a.account_code ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':as_of_date' => $as_of_date]);
            $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'balance_sheet':
            // Core Ledger balances for standard systemic evaluation equation
            $sql = "SELECT a.account_code, a.account_name, a.account_type,
                           SUM(d.debit) as total_debit, SUM(d.credit) as total_credit
                    FROM accounts a
                    LEFT JOIN transaction_details d ON a.id = d.account_id
                    LEFT JOIN transactions t ON d.transaction_id = t.id AND t.transaction_date <= :as_of_date
                    WHERE a.status = 'active' AND a.account_type IN ('Asset', 'Liability', 'Equity')
                    GROUP BY a.id
                    ORDER BY a.account_type ASC, a.account_code ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':as_of_date' => $as_of_date]);
            $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate Net Profit for Balance Sheet retention
            $pl_sql = "SELECT a.account_type, SUM(d.debit) as debits, SUM(d.credit) as credits
                       FROM accounts a
                       JOIN transaction_details d ON a.id = d.account_id
                       JOIN transactions t ON d.transaction_id = t.id AND t.transaction_date <= :as_of_date
                       WHERE a.account_type IN ('Revenue', 'Expense')
                       GROUP BY a.account_type";
            $pl_stmt = $pdo->prepare($pl_sql);
            $pl_stmt->execute([':as_of_date' => $as_of_date]);
            $pl_balances = $pl_stmt->fetchAll(PDO::FETCH_ASSOC);

            $net_profit = 0;
            foreach ($pl_balances as $pl) {
                if ($pl['account_type'] == 'Revenue') {
                    $net_profit += ($pl['credits'] - $pl['debits']);
                } else if ($pl['account_type'] == 'Expense') {
                    $net_profit -= ($pl['debits'] - $pl['credits']);
                }
            }
            break;
    }
} catch (PDOException $e) {
    $error_msg = "Reporting Pipeline Exception: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Intelligence Statements - Smart Choice Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }

        .main-content {
            margin-left: 260px;
            padding: 30px;
        }

        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
        }

        .report-header {
            border-bottom: 2px solid #343a40;
        }

        .table-summary {
            background-color: #e9ecef;
            font-weight: bold;
        }

        @media print {
            body * {
                visibility: hidden;
                background: #fff !important;
                color: #000 !important;
            }

            #printableReportArea,
            #printableReportArea * {
                visibility: visible;
            }

            #printableReportArea {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }

            .no-print {
                display: none !important;
            }
        }
    </style>
</head>

<body>

    <?php include '../../backend/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">

            <!-- Dashboard Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4 no-print">
                <div>
                    <h3 class="fw-bold text-dark mb-0">Financial Reporting Suite</h3>
                    <small class="text-muted">Generate automated accounting summaries from ledger nodes</small>
                </div>
                <button onclick="window.print()" class="btn btn-dark btn-sm px-4">
                    <i class="bi bi-printer-fill me-2"></i>Print Statement
                </button>
            </div>

            <!-- Configuration Selection Bar -->
            <div class="card mb-4 no-print">
                <div class="card-body p-3">
                    <form method="GET" action="" class="row g-2 align-items-end">
                        <div class="col-md-5">
                            <label class="form-label small fw-bold text-secondary">Target Report Type</label>
                            <select name="report_type" class="form-select form-select-sm">
                                <option value="trial_balance" <?= $selected_report === 'trial_balance' ? 'selected' : '' ?>>Trial Balance Sheet Matrix</option>
                                <option value="profit_loss" <?= $selected_report === 'profit_loss' ? 'selected' : '' ?>>Profit & Loss Statement (Income vs Outgoings)</option>
                                <option value="balance_sheet" <?= $selected_report === 'balance_sheet' ? 'selected' : '' ?>>Balance Sheet Statement (Asset & Liabilities Structural Check)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-secondary">Statement As-of Date</label>
                            <input type="date" name="as_of_date" class="form-control form-control-sm" value="<?= htmlspecialchars($as_of_date) ?>">
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary btn-sm w-100">Compile Report Engine</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger no-print"><?= $error_msg ?></div>
            <?php endif; ?>

            <!-- Printable Report Canvas Area -->
            <div class="card" id="printableReportArea">
                <div class="card-body p-4">

                    <!-- Core Institutional Header Branding Component -->
                    <div class="text-center report-header pb-3 mb-4">
                        <h3 class="fw-bold text-dark mb-1" style="letter-spacing:-0.5px;">SMART CHOICE ACADEMY</h3>
                        <p class="text-muted small mb-1">Main Sialkot Bypass, Gujranwala, Punjab</p>
                        <h5 class="text-uppercase font-monospace fw-bold text-primary tracking-wide mt-2">
                            <?php
                            if ($selected_report === 'trial_balance') echo "Trial Balance Matrix Ledger";
                            if ($selected_report === 'profit_loss') echo "Profit & Loss Income Statement";
                            if ($selected_report === 'balance_sheet') echo "Consolidated Statement of Balance Position";
                            ?>
                        </h5>
                        <small class="text-secondary font-monospace">Statement target operational index cap date up to: <strong><?= htmlspecialchars($as_of_date) ?></strong></small>
                    </div>

                    <!-- REPORT VIEW: TRIAL BALANCE -->
                    <?php if ($selected_report === 'trial_balance'): ?>
                        <table class="table table-bordered table-striped align-middle small">
                            <thead class="table-dark text-uppercase font-monospace text-center">
                                <tr>
                                    <th>Account Code</th>
                                    <th class="text-start">Account Ledger Node</th>
                                    <th>Classification Type</th>
                                    <th class="text-end">Debit Balance (PKR)</th>
                                    <th class="text-end">Credit Balance (PKR)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $total_debit_sum = $total_credit_sum = 0;
                                foreach ($report_data as $row):
                                    $net_debit = $net_credit = 0;
                                    // Identify normal account behaviors 
                                    if (in_array($row['account_type'], ['Asset', 'Expense'])) {
                                        $net_debit = $row['total_debit'] - $row['total_credit'];
                                        if ($net_debit < 0) {
                                            $net_credit = abs($net_debit);
                                            $net_debit = 0;
                                        }
                                    } else {
                                        $net_credit = $row['total_credit'] - $row['total_debit'];
                                        if ($net_credit < 0) {
                                            $net_debit = abs($net_credit);
                                            $net_credit = 0;
                                        }
                                    }
                                    if ($net_debit == 0 && $net_credit == 0) continue;
                                    $total_debit_sum += $net_debit;
                                    $total_credit_sum += $net_credit;
                                ?>
                                    <tr>
                                        <td class="font-monospace fw-bold text-center"><?= htmlspecialchars($row['account_code']) ?></td>
                                        <td class="fw-semibold text-dark"><?= htmlspecialchars($row['account_name']) ?></td>
                                        <td class="text-center font-monospace text-muted"><?= $row['account_type'] ?></td>
                                        <td class="text-end text-success fw-semibold"><?= $net_debit > 0 ? number_format($net_debit, 2) : '-' ?></td>
                                        <td class="text-end text-danger fw-semibold"><?= $net_credit > 0 ? number_format($net_credit, 2) : '-' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="table-summary font-monospace fs-6">
                                    <td colspan="3" class="text-center text-uppercase">Aggregated Balances Check</td>
                                    <td class="text-end text-success">Rs. <?= number_format($total_debit_sum, 2) ?></td>
                                    <td class="text-end text-danger">Rs. <?= number_format($total_credit_sum, 2) ?></td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- REPORT VIEW: PROFIT & LOSS -->
                    <?php elseif ($selected_report === 'profit_loss'): ?>
                        <div class="row justify-content-center">
                            <div class="col-lg-10">
                                <h6 class="fw-bold text-success border-bottom pb-1 text-uppercase font-monospace"><i class="bi bi-graph-up-arrow me-2"></i>Operating Fee Revenues</h6>
                                <table class="table table-borderless table-sm mb-4 small">
                                    <?php
                                    $total_revenue = 0;
                                    foreach ($report_data as $row):
                                        if ($row['account_type'] !== 'Revenue') continue;
                                        $balance = $row['total_credit'] - $row['total_debit'];
                                        $total_revenue += $balance;
                                    ?>
                                        <tr>
                                            <td class="ps-3 text-muted font-monospace" style="width:20%;"><?= $row['account_code'] ?></td>
                                            <td class="fw-semibold text-dark"><?= htmlspecialchars($row['account_name']) ?></td>
                                            <td class="text-end fw-bold text-dark" style="width:25%;">Rs. <?= number_format($balance, 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="border-top fw-bold">
                                        <td colspan="2" class="text-uppercase ps-2">Gross Accumulated Revenue Stream</td>
                                        <td class="text-end text-success fs-6">Rs. <?= number_format($total_revenue, 2) ?></td>
                                    </tr>
                                </table>

                                <h6 class="fw-bold text-danger border-bottom pb-1 text-uppercase font-monospace"><i class="bi bi-graph-down-arrow me-2"></i>Operating Expenditures</h6>
                                <table class="table table-borderless table-sm mb-4 small">
                                    <?php
                                    $total_expense = 0;
                                    foreach ($report_data as $row):
                                        if ($row['account_type'] !== 'Expense') continue;
                                        $balance = $row['total_debit'] - $row['total_credit'];
                                        $total_expense += $balance;
                                    ?>
                                        <tr>
                                            <td class="ps-3 text-muted font-monospace" style="width:20%;"><?= $row['account_code'] ?></td>
                                            <td class="fw-semibold text-dark"><?= htmlspecialchars($row['account_name']) ?></td>
                                            <td class="text-end fw-bold text-dark" style="width:25%;">Rs. <?= number_format($balance, 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="border-top fw-bold">
                                        <td colspan="2" class="text-uppercase ps-2">Total System Overhead Distributions</td>
                                        <td class="text-end text-danger fs-6">Rs. <?= number_format($total_expense, 2) ?></td>
                                    </tr>
                                </table>

                                <?php $net_margin = $total_revenue - $total_expense; ?>
                                <div class="card bg-light border border-secondary-subtle mt-4">
                                    <div class="card-body d-flex justify-content-between align-items-center p-3 font-monospace">
                                        <h5 class="fw-bold mb-0 text-uppercase">Net Operational Margin Earnings:</h5>
                                        <h4 class="fw-bold mb-0 <?= $net_margin >= 0 ? 'text-success' : 'text-danger' ?>">
                                            Rs. <?= number_format($net_margin, 2) ?>
                                        </h4>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- REPORT VIEW: BALANCE SHEET -->
                    <?php elseif ($selected_report === 'balance_sheet'): ?>
                        <div class="row">
                            <!-- Left Block Side: Assets Management Track -->
                            <div class="col-md-6 border-end">
                                <h6 class="fw-bold text-primary border-bottom pb-1 text-uppercase font-monospace">Assets Ledger Tracking Matrix</h6>
                                <table class="table table-borderless table-sm small">
                                    <?php
                                    $total_assets = 0;
                                    foreach ($report_data as $row):
                                        if ($row['account_type'] !== 'Asset') continue;
                                        $balance = $row['total_debit'] - $row['total_credit'];
                                        $total_assets += $balance;
                                    ?>
                                        <tr>
                                            <td class="text-muted font-monospace" style="width:25%;"><?= $row['account_code'] ?></td>
                                            <td><?= htmlspecialchars($row['account_name']) ?></td>
                                            <td class="text-end fw-bold text-dark">Rs. <?= number_format($balance, 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="border-top fw-bold font-monospace">
                                        <td colspan="2" class="text-uppercase text-primary">Total Assets Valuation</td>
                                        <td class="text-end text-primary">Rs. <?= number_format($total_assets, 2) ?></td>
                                    </tr>
                                </table>
                            </div>

                            <!-- Right Block Side: Capital Liabilities & Reserves Matrix -->
                            <div class="col-md-6">
                                <h6 class="fw-bold text-secondary border-bottom pb-1 text-uppercase font-monospace">Liabilities & Equity</h6>
                                <table class="table table-borderless table-sm small mb-3">
                                    <?php
                                    $total_liab_equity = 0;
                                    foreach ($report_data as $row):
                                        if (!in_array($row['account_type'], ['Liability', 'Equity'])) continue;
                                        $balance = $row['total_credit'] - $row['total_debit'];
                                        $total_liab_equity += $balance;
                                    ?>
                                        <tr>
                                            <td class="text-muted font-monospace" style="width:25%;"><?= $row['account_code'] ?></td>
                                            <td><?= htmlspecialchars($row['account_name']) ?></td>
                                            <td class="text-end fw-bold text-dark">Rs. <?= number_format($balance, 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>

                                    <!-- Dynamic Addition of calculated P&L Profit Margins to Balance Sheet -->
                                    <tr>
                                        <td class="text-muted font-monospace">-</td>
                                        <td class="fst-italic text-dark">Retained Earnings Margin (P&L Context Accumulation)</td>
                                        <td class="text-end fw-bold text-dark">Rs. <?= number_format($net_profit, 2) ?></td>
                                    </tr>
                                    <?php $total_liab_equity += $net_profit; ?>

                                    <tr class="border-top fw-bold font-monospace">
                                        <td colspan="2" class="text-uppercase text-secondary">Total Capital Obligations & Equity</td>
                                        <td class="text-end text-secondary">Rs. <?= number_format($total_liab_equity, 2) ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <!-- System Equations Integrity Validation Banner -->
                        <div class="alert mt-4 p-2 text-center font-monospace border <?= abs($total_assets - $total_liab_equity) < 0.01 ? 'alert-success border-success-subtle' : 'alert-danger border-danger-subtle' ?>" style="font-size:0.9rem;">
                            <strong>System Formula Audit Verification Tracker:</strong>
                            Assets (Rs. <?= number_format($total_assets, 2) ?>)
                            <?= abs($total_assets - $total_liab_equity) < 0.01 ? '===' : '=/=' ?>
                            Liabilities + Equity (Rs. <?= number_format($total_liab_equity, 2) ?>)
                        </div>
                    <?php endif; ?>

                    <!-- Auditor Handshake Signatures Footprint Block -->
                    <div class="row pt-5 mt-4 text-center small font-monospace">
                        <div class="col-6">
                            <div class="border-top border-dark mx-5 pt-2 text-muted">Generated Automatically By SCA Engine</div>
                        </div>
                        <div class="col-6">
                            <div class="border-top border-dark mx-5 pt-2 text-dark font-weight-bold">Authorized Comptroller Clearance Sign</div>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>

</body>

</html>