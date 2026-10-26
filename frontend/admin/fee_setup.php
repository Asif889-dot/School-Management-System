<?php
require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();
requireRole('admin');

// Fetch necessary data for dropdowns
$academicYears = $pdo->query("SELECT * FROM academic_years WHERE status = 'active' ORDER BY year_name DESC")->fetchAll();
$classes = $pdo->query("SELECT * FROM classes ORDER BY numeric_grade ASC")->fetchAll();
$feeTypes = $pdo->query("SELECT * FROM fee_types ORDER BY name ASC")->fetchAll();

$message = $_SESSION['fee_message'] ?? '';
$messageType = $_SESSION['fee_message_type'] ?? '';
unset($_SESSION['fee_message'], $_SESSION['fee_message_type']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Structure Setup | SMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --accent-purple: #764ba2;
        }

        .fee-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            background: #fff;
            overflow: hidden;
        }

        .form-header {
            background: var(--primary-gradient);
            color: white;
            padding: 25px;
            text-align: center;
        }

        .section-title {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--accent-purple);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            border-bottom: 2px solid #f1f1f1;
            padding-bottom: 8px;
            margin-bottom: 25px;
        }

        .form-control,
        .form-select {
            border: 2px solid #edf2f7;
            border-radius: 12px;
            padding: 12px 15px;
        }

        .btn-submit {
            background: var(--primary-gradient);
            border: none;
            padding: 12px 40px;
            border-radius: 12px;
            font-weight: 600;
            color: white;
            transition: 0.4s;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            background: #f3f4f6;
            padding: 20px;
        }
    </style>
</head>

<body>
    <?php include '../../backend/sidebar.php'; ?>

    <main class="main-content">
        <header class="top-header">
            <h5 class="mb-0"><i class="bi bi-cash-stack me-2"></i>Fee Management</h5>
        </header>

        <div class="dashboard-content">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row justify-content-center">
                <div class="col-xl-10">
                    <div class="fee-card">
                        <div class="form-header">
                            <h4 class="fw-bold mb-1">Create Fee Structure</h4>
                            <p class="mb-0 opacity-75">Define fees for classes and sessions</p>
                        </div>

                        <form action="../../backend/fee_structure_save.php" method="POST" class="p-4 p-md-5">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Academic Session</label>
                                    <select class="form-select" name="academic_year_id" required>
                                        <option value="">Select Session</option>
                                        <?php foreach ($academicYears as $year): ?>
                                            <option value="<?= $year['id'] ?>"><?= htmlspecialchars($year['year_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Target Class</label>
                                    <select class="form-select" name="class_id" required>
                                        <option value="">Select Class</option>
                                        <?php foreach ($classes as $class): ?>
                                            <option value="<?= $class['id'] ?>"><?= htmlspecialchars($class['class_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Fee Type</label>
                                    <select class="form-select" name="fee_type_id" required>
                                        <option value="">Select Type</option>
                                        <?php foreach ($feeTypes as $type): ?>
                                            <option value="<?= $type['id'] ?>"><?= htmlspecialchars($type['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Amount (PKR)</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-2 border-end-0" style="border-radius: 12px 0 0 12px;">Rs.</span>
                                        <input type="number" step="0.01" class="form-control" name="amount" placeholder="0.00" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Payment Frequency</label>
                                    <select class="form-select" name="frequency" required>
                                        <option value="monthly">Monthly</option>
                                        <option value="quarterly">Quarterly</option>
                                        <option value="yearly">Yearly / One-time</option>
                                    </select>
                                </div>

                                <div class="col-12 text-center pt-3">
                                    <button type="submit" class="btn btn-submit shadow">Save Fee Structure</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>