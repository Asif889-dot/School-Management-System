<?php
require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();

$search = $_GET['search'] ?? '';

// Fetch last 20 payments or search by Receipt No or Student Name
$query = "SELECT fp.receipt_no, fp.payment_date, fp.payment_method, 
                 SUM(fp.amount_paid) as total_amount, 
                 s.first_name, s.last_name, s.reg_id
          FROM fee_payments fp
          JOIN students s ON fp.student_id = s.id
          WHERE fp.receipt_no LIKE ? OR s.first_name LIKE ? OR s.reg_id LIKE ?
          GROUP BY fp.receipt_no 
          ORDER BY fp.payment_date DESC LIMIT 50";

$stmt = $pdo->prepare($query);
$stmt->execute(["%$search%", "%$search%", "%$search%"]);
$history = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Payment History | SMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
</head>

<body class="bg-light">
    <?php include '../../backend/sidebar.php'; ?>

    <main class="main-content" style="margin-left: 260px; padding: 30px;">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">Fee Payment History</h5>
            </div>
            <div class="card-body">
                <form class="row g-3 mb-4">
                    <div class="col-md-10">
                        <input type="text" name="search" class="form-control"
                            placeholder="Search by Receipt No, Student Name, or Reg ID..."
                            value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Search</button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Receipt No</th>
                                <th>Student</th>
                                <th>Total Paid</th>
                                <th>Method</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history as $row): ?>
                                <tr>
                                    <td><?= date('d-m-Y', strtotime($row['payment_date'])) ?></td>
                                    <td class="fw-bold text-primary"><?= $row['receipt_no'] ?></td>
                                    <td>
                                        <?= $row['first_name'] ?> (<?= $row['reg_id'] ?>)
                                    </td>
                                    <td>Rs. <?= number_format($row['total_amount'], 2) ?></td>
                                    <td><span class="badge bg-info"><?= $row['payment_method'] ?></span></td>
                                    <td class="text-center">
                                        <a href="receipt_view.php?receipt=<?= $row['receipt_no'] ?>"
                                            class="btn btn-sm btn-outline-dark">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>