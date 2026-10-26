<?php
/**
 * Smart Choice Academy - Journal Voucher Module
 * Manages, filters, displays, and prints double-entry voucher records.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Adjust database connection path based on your architecture
require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();

$error_msg = "";
$search_query = "";
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

try {
    // Base SQL Query for master vouchers
    $sql = "SELECT t.*, u.full_name AS operator_name 
            FROM transactions t
            LEFT JOIN users u ON t.created_by = u.id 
            WHERE 1=1";
    $params = [];

    // Filter by start date
    if (!empty($start_date)) {
        $sql .= " AND t.transaction_date >= :start_date";
        $params[':start_date'] = $start_date;
    }

    // Filter by end date
    if (!empty($end_date)) {
        $sql .= " AND t.transaction_date <= :end_date";
        $params[':end_date'] = $end_date;
    }

    // Filter by keyword (Voucher No, Reference, Description)
    if (!empty($search)) {
        $sql .= " AND (t.voucher_no LIKE :search OR t.reference LIKE :search OR t.description LIKE :search)";
        $params[':search'] = "%" . $search . "%";
    }

    $sql .= " ORDER BY t.transaction_date DESC, t.id DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_msg = "Database Connection / Execution Error: " . $e->getMessage();
}

// AJAX request handler to fetch granular ledger rows for modal print/view previews
if (isset($_GET['action']) && $_GET['action'] === 'fetch_details' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $trx_id = intval($_GET['id']);
    try {
        $detail_stmt = $pdo->prepare("
            SELECT d.*, a.account_name, a.account_code 
            FROM transaction_details d
            JOIN accounts a ON d.account_id = a.id
            WHERE d.transaction_id = ?
            ORDER BY d.debit DESC, d.id ASC
        ");
        $detail_stmt->execute([$trx_id]);
        echo json_encode($detail_stmt->fetchAll(PDO::FETCH_ASSOC)); // FIXED: Changed json_json_encode to json_encode
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]); // FIXED: Changed json_json_encode to json_encode
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Journal Vouchers - Smart Choice Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; }
        .main-content { margin-left: 260px; padding: 30px; transition: all 0.3s ease; }
        .card { border: none; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.04); }
        .table-responsive { background: #fff; border-radius: 10px; }
        
        /* Print Stylesheet Overrides for clean Vouchers */
        @media print {
            body * { visibility: hidden; background: #fff !important; color: #000 !important; }
            #printArea, #printArea * { visibility: visible; }
            #printArea { position: absolute; left: 0; top: 0; width: 100%; padding: 20px; }
            .no-print { display: none !important; }
        }
        @media (max-width: 768px) { .main-content { margin-left: 0; padding: 15px; } }
    </style>
</head>
<body>

    <?php include '../../backend/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold text-dark mb-0">Journal Vouchers</h3>
                    <small class="text-muted">Audit trail and official double-entry transaction slips</small>
                </div>
                <a href="add_transaction.php" class="btn btn-primary btn-sm px-3">
                    <i class="bi bi-plus-lg me-1"></i> New Journal Entry
                </a>
            </div>

            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger mb-4"><i class="bi bi-exclamation-octagon me-2"></i><?= $error_msg ?></div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-body p-3">
                    <form method="GET" action="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-secondary">Start Date</label>
                            <input type="date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($start_date) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-secondary">End Date</label>
                            <input type="date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($end_date) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-secondary">Keyword Search</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white text-muted"><i class="bi bi-search"></i></span>
                                <input type="text" name="search" class="form-control" placeholder="Search Voucher No, Ref, or Description..." value="<?= htmlspecialchars($search) ?>">
                            </div>
                        </div>
                        <div class="col-md-2 d-flex gap-1">
                            <button type="submit" class="btn btn-dark btn-sm w-100">Filter</button>
                            <a href="journal_voucher.php" class="btn btn-light btn-sm w-100 border">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-uppercase font-monospace small">
                                <tr>
                                    <th class="ps-4 py-3">Date</th>
                                    <th>Voucher Number</th>
                                    <th>Reference ID</th>
                                    <th>Narration Summary</th>
                                    <th class="text-end">Voucher Total</th>
                                    <th class="text-center pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="small">
                                <?php if (empty($vouchers)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <i class="bi bi-folder-x display-6 d-block mb-2 text-secondary"></i>
                                            No journal vouchers matching criteria found.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($vouchers as $v): ?>
                                        <tr>
                                            <td class="ps-4 fw-semibold text-secondary"><?= htmlspecialchars($v['transaction_date']) ?></td>
                                            <td><span class="badge bg-light text-dark border font-monospace fw-bold px-2 py-1.5"><?= htmlspecialchars($v['voucher_no']) ?></span></td>
                                            <td class="text-dark font-monospace"><?= !empty($v['reference']) ? htmlspecialchars($v['reference']) : '<span class="text-muted">-</span>' ?></td>
                                            <td class="text-muted text-wrap" style="max-width: 320px;"><?= htmlspecialchars($v['description']) ?></td>
                                            <td class="text-end fw-bold text-dark">Rs. <?= number_format($v['total_amount'], 2) ?></td>
                                            <td class="text-center pe-4">
                                                <button class="btn btn-outline-secondary btn-sm rounded-pill px-2.5 py-1" 
                                                        onclick="openVoucherModal(<?= htmlspecialchars(json_encode($v)) ?>)"> <i class="bi bi-printer-fill me-1"></i> View / Print
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <div class="modal fade" id="voucherModal" tabindex="-1" aria-labelledby="voucherModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header no-print">
                    <h5 class="modal-title fw-bold" id="voucherModalLabel"><i class="bi bi-file-earmark-bar-graph me-2 text-primary"></i>Accounting Voucher Manifest</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="printArea">
                    <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-4">
                        <div>
                            <h4 class="fw-bold text-primary mb-0" style="letter-spacing: -0.5px;">SMART CHOICE ACADEMY</h4>
                            <p class="text-muted small mb-0">Main Sialkot Bypass, Gujranwala, Punjab</p>
                            <small class="text-muted font-monospace text-uppercase">Double-Entry Financial Voucher System</small>
                        </div>
                        <div class="text-end">
                            <h5 class="text-uppercase border border-dark rounded px-3 py-1 bg-light font-monospace fw-bold mb-1">Journal Voucher</h5>
                            <small class="text-muted d-block">System Printed: <?= date('Y-m-d H:i:s') ?></small>
                        </div>
                    </div>

                    <div class="row g-3 bg-light rounded p-3 mb-4 border border-light-subtle small">
                        <div class="col-6 col-sm-3">
                            <span class="text-muted d-block text-uppercase font-monospace small">Voucher Code</span>
                            <strong id="m_voucher_no" class="font-monospace text-dark fs-6">-</strong>
                        </div>
                        <div class="col-6 col-sm-3">
                            <span class="text-muted d-block text-uppercase font-monospace small">Posting Date</span>
                            <strong id="m_date" class="text-dark fs-6">-</strong>
                        </div>
                        <div class="col-6 col-sm-3">
                            <span class="text-muted d-block text-uppercase font-monospace small">Doc Reference #</span>
                            <strong id="m_reference" class="font-monospace text-dark fs-6">-</strong>
                        </div>
                        <div class="col-6 col-sm-3">
                            <span class="text-muted d-block text-uppercase font-monospace small">Issued By Authorized</span>
                            <strong id="m_operator" class="text-dark fs-6">-</strong>
                        </div>
                    </div>

                    <div class="mb-4">
                        <h6 class="fw-bold text-secondary mb-1 text-uppercase font-monospace small">Main Narration / Description Context</h6>
                        <p id="m_description" class="bg-body-tertiary p-2.5 rounded border border-light-subtle text-dark text-wrap mb-0" style="font-size:0.92rem;"></p>
                    </div>

                    <h6 class="fw-bold text-secondary mb-2 text-uppercase font-monospace small">Double-Entry Ledger Account Adjustments Matrix</h6>
                    <table class="table table-bordered align-middle small mb-4">
                        <thead class="table-dark font-monospace text-uppercase text-center">
                            <tr>
                                <th class="text-start" style="width: 50%;">Account Code & Account Head Name</th>
                                <th style="width: 25%;">Debit Amount (PKR)</th>
                                <th style="width: 25%;">Credit Amount (PKR)</th>
                            </tr>
                        </thead>
                        <tbody id="modal_ledger_rows">
                            </tbody>
                        <tfoot class="table-light fw-bold font-monospace text-end fs-6">
                            <tr>
                                <td class="text-center text-uppercase text-muted">Voucher Balance Integrity Totals</td>
                                <td id="total_debit_cell" class="text-success text-end">Rs. 0.00</td>
                                <td id="total_credit_cell" class="text-danger text-end">Rs. 0.00</td>
                            </tr>
                        </tfoot>
                    </table>

                    <div class="row pt-5 mt-5 text-center small font-monospace">
                        <div class="col-4">
                            <div class="border-top border-dark mx-3 pt-2">Prepared By / Cashier</div>
                        </div>
                        <div class="col-4">
                            <div class="border-top border-dark mx-3 pt-2">Audited / Checked By</div>
                        </div>
                        <div class="col-4">
                            <div class="border-top border-dark mx-3 pt-2">Principal / Admin Sign</div>
                        </div>
                    </div>

                </div>
                <div class="modal-footer no-print">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close Window</button>
                    <button type="button" class="btn btn-success px-4" onclick="window.print()">
                        <i class="bi bi-printer me-1"></i> Execute Print Form
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Init state variables
        const bootstrapModal = new bootstrap.Modal(document.getElementById('voucherModal'));

        /**
         * Orchestrates modal parsing arrays, querying database dynamically via AJAX backend hook 
         */
        function openVoucherModal(voucher) {
            // Bind header master details variables
            document.getElementById('m_voucher_no').innerText = voucher.voucher_no || 'N/A';
            document.getElementById('m_date').innerText = voucher.transaction_date || 'N/A';
            document.getElementById('m_reference').innerText = voucher.reference || 'None Logged';
            document.getElementById('m_operator').innerText = voucher.operator_name || 'System Automated';
            document.getElementById('m_description').innerText = voucher.description || '';

            const tableBody = document.getElementById('modal_ledger_rows');
            tableBody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3"><div class="spinner-border spinner-border-sm me-2" role="status"></div>Retrieving ledger alignment tracks...</td></tr>';

            // Fire endpoint query lookup back to page to fetch underlying debit/credit logs securely
            fetch(`journal_voucher.php?action=fetch_details&id=${voucher.id}`)
                .then(response => response.json())
                .then(data => {
                    tableBody.innerHTML = '';
                    let sumDebits = 0;
                    let sumCredits = 0;

                    if(data.error || !Array.isArray(data)) {
                        tableBody.innerHTML = `<tr><td colspan="3" class="text-center text-danger">Error retrieving row data adjustments metrics: ${data.error || 'Syntax parsing mismatch'}</td></tr>`;
                        return;
                    }

                    data.forEach(item => {
                        const debitVal = parseFloat(item.debit) || 0;
                        const creditVal = parseFloat(item.credit) || 0;
                        sumDebits += debitVal;
                        sumCredits += creditVal;

                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td class="ps-3">
                                <strong class="text-secondary">[${item.account_code}]</strong> 
                                <span class="text-dark fw-semibold ms-1">${item.account_name}</span>
                                <div class="text-muted text-wrap x-small mt-0.5" style="font-size:0.8rem;">${item.description || ''}</div>
                            </td>
                            <td class="text-end text-success fw-bold">${debitVal > 0 ? 'Rs. ' + debitVal.toFixed(2) : '-'}</td>
                            <td class="text-end text-danger fw-bold">${creditVal > 0 ? 'Rs. ' + creditVal.toFixed(2) : '-'}</td>
                        `; // FIXED: Added missing closing </tr> tag in dynamic Javascript template literal template layout string
                        tableBody.appendChild(row);
                    });

                    // Set balance checking totals dynamically inside view
                    document.getElementById('total_debit_cell').innerText = 'Rs. ' + sumDebits.toFixed(2);
                    document.getElementById('total_credit_cell').innerText = 'Rs. ' + sumCredits.toFixed(2);
                })
                .catch(err => {
                    tableBody.innerHTML = `<tr><td colspan="3" class="text-center text-danger">System runtime failure processing fetch operations hook.</td></tr>`;
                });

            // Deploy modal window
            bootstrapModal.show();
        }
    </script>
</body>
</html>