<?php
require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();
requireRole('admin');

$sessions = $pdo->query("SELECT * FROM academic_years WHERE status = 'active' ORDER BY year_name DESC")->fetchAll();
$classes = $pdo->query("SELECT * FROM classes ORDER BY numeric_grade ASC")->fetchAll();
$sections = $pdo->query("SELECT * FROM sections ORDER BY section_name ASC")->fetchAll();
$students = $pdo->query("SELECT id, reg_id, first_name, last_name, class_id, section_id, academic_year_id FROM students WHERE status = 'approved'")->fetchAll();

$feeStructures = $pdo->query("
    SELECT fs.id, fs.amount, fs.frequency, fs.class_id, ft.name as fee_name 
    FROM fee_structures fs
    JOIN fee_types ft ON fs.fee_type_id = ft.id
")->fetchAll();

// Generate a unique receipt number for this session
$generated_receipt = "RCP-" . strtoupper(substr(uniqid(), 7));
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Multi-Fee Collection | SMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .collect-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            background: #fff;
        }

        .receipt-header {
            background: var(--primary-gradient);
            color: white;
            padding: 20px;
            border-radius: 20px 20px 0 0;
        }

        .filter-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 12px;
            border: 1px solid #dee2e6;
            margin-bottom: 20px;
        }

        .fee-table {
            background: #fff;
            border-radius: 10px;
            overflow: hidden;
        }

        .total-display {
            font-size: 1.5rem;
            font-weight: 700;
            color: #764ba2;
        }

        .btn-collect {
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            transition: 0.3s;
        }

        .btn-collect:hover {
            opacity: 0.9;
            color: white;
            transform: translateY(-2px);
        }
    </style>
</head>

<body>
    <?php include '../../backend/sidebar.php'; ?>

    <main class="main-content" style="margin-left: 260px; padding: 30px;">
        <div class="row justify-content-center">
            <div class="col-xl-11">

                <?php if (isset($_GET['status']) && $_GET['status'] == 'success'): ?>
                    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        Payment recorded successfully! <strong>Receipt: <?= htmlspecialchars($_GET['receipt']) ?></strong>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="collect-card">
                    <div class="receipt-header text-center">
                        <h4 class="mb-0"><i class="bi bi-layers-half me-2"></i> Bulk Fee Collection</h4>
                    </div>

                    <!-- Added validation gateway inside the onsubmit handler -->
                    <form action="../../backend/process_payment_bulk.php" method="POST" class="p-4" id="feeForm" onsubmit="return validateFormSubmission();">

                        <div class="filter-section">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="small fw-bold text-muted">Academic Session</label>
                                    <select id="filter_session" class="form-select" onchange="filterStudents()">
                                        <option value="">All Sessions</option>
                                        <?php foreach ($sessions as $s): ?>
                                            <option value="<?= $s['id'] ?>"><?= $s['year_name'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="small fw-bold text-muted">Class</label>
                                    <select id="filter_class" class="form-select" onchange="filterStudents(); filterFees();">
                                        <option value="">Select Class</option>
                                        <?php foreach ($classes as $c): ?>
                                            <option value="<?= $c['id'] ?>"><?= $c['class_name'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="small fw-bold text-muted">Section</label>
                                    <select id="filter_section" class="form-select" onchange="filterStudents()">
                                        <option value="">All Sections</option>
                                        <?php foreach ($sections as $sec): ?>
                                            <option value="<?= $sec['id'] ?>"><?= $sec['section_name'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row g-4">
                            <div class="col-lg-5">
                                <div class="p-3 border rounded shadow-sm bg-light">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">1. Select Student</label>
                                        <select class="form-select form-control-lg border-primary" name="student_id" id="student_select" required>
                                            <option value="">Select Student...</option>
                                            <?php foreach ($students as $std): ?>
                                                <option value="<?= $std['id'] ?>" data-session="<?= $std['academic_year_id'] ?>" data-class="<?= $std['class_id'] ?>" data-section="<?= $std['section_id'] ?>">
                                                    <?= $std['reg_id'] ?> - <?= $std['first_name'] ?> <?= $std['last_name'] ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-bold">2. Add Fee Items</label>
                                        <div class="input-group">
                                            <select class="form-select" id="fee_selector">
                                                <option value="">Choose Fee Type...</option>
                                                <?php foreach ($feeStructures as $fs): ?>
                                                    <option value="<?= $fs['id'] ?>" data-class="<?= $fs['class_id'] ?>" data-amount="<?= $fs['amount'] ?>" data-name="<?= $fs['fee_name'] ?>">
                                                        <?= $fs['fee_name'] ?> (Rs. <?= $fs['amount'] ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="button" class="btn btn-primary" onclick="addFeeRow()"><i class="bi bi-plus-lg"></i> Add</button>
                                        </div>
                                    </div>

                                    <hr>

                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <label class="small fw-bold">Date</label>
                                            <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="small fw-bold">Method</label>
                                            <select class="form-select" name="payment_method">
                                                <option value="Cash">Cash</option>
                                                <option value="Bank">Bank</option>
                                                <option value="Online">Online</option>
                                            </select>
                                        </div>
                                        <div class="col-md-12 mt-2">
                                            <label class="small fw-bold">Receipt No</label>
                                            <input type="text" name="receipt_no" class="form-control bg-white fw-bold text-primary" value="<?= $generated_receipt ?>" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-7">
                                <div class="table-responsive fee-table border">
                                    <table class="table table-hover mb-0" id="selectedFeeTable">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>Fee Description</th>
                                                <th class="text-end">Amount</th>
                                                <th class="text-center">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody id="feeList">
                                        </tbody>
                                        <tfoot class="table-light">
                                            <tr>
                                                <td class="fw-bold">Total Payable</td>
                                                <td class="text-end total-display" id="grandTotalDisplay">Rs. 0.00</td>
                                                <td></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>

                                <div class="mt-4 text-end">
                                    <button type="submit" class="btn btn-collect btn-lg shadow">
                                        <i class="bi bi-printer me-2"></i> Record All & Print
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let selectedFees = [];

        function addFeeRow() {
            const selector = document.getElementById('fee_selector');
            const selectedOption = selector.options[selector.selectedIndex];

            if (!selectedOption.value) return;

            const feeId = selectedOption.value;
            const feeName = selectedOption.getAttribute('data-name');
            const amount = parseFloat(selectedOption.getAttribute('data-amount'));

            if (selectedFees.includes(feeId)) {
                alert("This fee item is already added.");
                return;
            }

            selectedFees.push(feeId);

            const tbody = document.getElementById('feeList');
            const row = document.createElement('tr');
            row.id = `fee-row-${feeId}`;
            row.innerHTML = `
                <td class="align-middle">
                    ${feeName}
                    <input type="hidden" name="fee_ids[]" value="${feeId}">
                    <input type="hidden" name="amounts[]" value="${amount}">
                </td>
                <td class="text-end align-middle fw-bold">Rs. ${amount.toFixed(2)}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeFeeRow('${feeId}')">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(row);
            calculateTotal();
        }

        // Destroys the selected table row container and updates the internal collection state arrays
        function removeFeeRow(id) {
            const targetedRow = document.getElementById(`fee-row-${id}`);
            if (targetedRow) {
                targetedRow.remove();
            }
            selectedFees = selectedFees.filter(feeId => feeId !== id);
            calculateTotal();
        }

        function calculateTotal() {
            const amounts = document.getElementsByName('amounts[]');
            let total = 0;
            amounts.forEach(input => {
                total += parseFloat(input.value);
            });
            document.getElementById('grandTotalDisplay').innerText = `Rs. ${total.toFixed(2)}`;
        }

        function filterStudents() {
            const session = document.getElementById('filter_session').value;
            const classId = document.getElementById('filter_class').value;
            const section = document.getElementById('filter_section').value;
            const studentOptions = document.querySelectorAll('#student_select option');

            studentOptions.forEach(opt => {
                if (opt.value === "") return;
                const matchSession = !session || opt.getAttribute('data-session') === session;
                const matchClass = !classId || opt.getAttribute('data-class') === classId;
                const matchSection = !section || opt.getAttribute('data-section') === section;
                opt.style.display = (matchSession && matchClass && matchSection) ? "block" : "none";
            });
            document.getElementById('student_select').value = "";
            clearTable();
        }

        function filterFees() {
            const classId = document.getElementById('filter_class').value;
            const feeOptions = document.querySelectorAll('#fee_selector option');
            feeOptions.forEach(opt => {
                if (opt.value === "") return;
                opt.style.display = (!classId || opt.getAttribute('data-class') === classId) ? "block" : "none";
            });
            document.getElementById('fee_selector').value = "";
        }

        function clearTable() {
            document.getElementById('feeList').innerHTML = "";
            selectedFees = [];
            calculateTotal();
        }

        // System protection gateway: halts submissions if the ledger item list is vacant
        function validateFormSubmission() {
            if (selectedFees.length === 0) {
                alert("Operation Aborted: Please append at least one explicit fee structure line item before transmitting collection logs to the ledger system.");
                return false;
            }
            return true;
        }
    </script>
</body>

</html>