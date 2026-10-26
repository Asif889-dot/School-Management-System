<?php
require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();

try {
    // Fetch all structural fees grouped by Year, Class, and Category type
    $query = "SELECT 
                fs.id as structure_id,
                ay.year_name,
                ay.is_current,
                c.class_name,
                ft.name as fee_name,
                fs.amount,
                fs.frequency,
                fs.due_date
              FROM fee_structures fs
              JOIN academic_years ay ON fs.academic_year_id = ay.id
              JOIN classes c ON fs.class_id = c.id
              JOIN fee_types ft ON fs.fee_type_id = ft.id
              ORDER BY ay.is_current DESC, ay.year_name DESC, c.numeric_grade ASC, ft.name ASC";

    $stmt = $pdo->query($query);
    $fee_records = $stmt->fetchAll();

    // Group results by academic session and grade level for a cleaner layout view
    $grouped_fees = [];
    foreach ($fee_records as $row) {
        $session_label = $row['year_name'] . ($row['is_current'] === 'yes' ? ' (Active Session)' : '');
        $grouped_fees[$session_label][$row['class_name']][] = $row;
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Fee Configurations - Smart Choice Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <style>
        body {
            background-color: #f8f9fa;
        }

        .session-card {
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .class-header {
            background-color: #e9ecef;
            font-weight: 600;
            color: #495057;
        }

        .badge-frequency {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
    </style>
</head>

<body>
    <?php include '../../backend/sidebar.php'; ?>
    <main class="main-content">
        <div class="container my-5">
            <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                <div>
                    <h2 class="fw-bold text-dark">Fee Structure Configurations</h2>
                    <p class="text-muted mb-0">Overview of configured fees across different school classes and terms.</p>
                </div>
                <div>
                    <a href="fee_setup.php" class="btn btn-dark">+ Add New Fee Rule</a>
                </div>
            </div>

            <?php if (empty($grouped_fees)): ?>
                <div class="alert alert-warning text-center p-4" role="alert">
                    <h5 class="alert-heading fw-bold mb-2">No Fee Records Configured Yet</h5>
                    <p class="mb-0">Please map rates within your <code>fee_structures</code> and <code>fee_types</code> database matrix tables first.</p>
                </div>
            <?php else: ?>

                <?php foreach ($grouped_fees as $session_name => $classes): ?>
                    <div class="card session-card bg-white border-0">
                        <div class="card-header bg-dark text-white p-3 rounded-top d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 fw-semibold">Academic Session: <?= htmlspecialchars($session_name) ?></h5>
                        </div>
                        <div class="card-body p-0">

                            <?php foreach ($classes as $class_name => $fees): ?>
                                <div class="px-4 py-2 class-header border-bottom">
                                    <?= htmlspecialchars($class_name) ?>
                                </div>

                                <div class="table-responsive px-4 py-2">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead>
                                            <tr class="text-muted small">
                                                <th style="width: 40%;">Fee Component Type</th>
                                                <th style="width: 25%;">Billing Interval Frequency</th>
                                                <th style="width: 15%;" class="text-end">Base Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $class_total = 0;
                                            foreach ($fees as $fee):
                                                $class_total += $fee['amount'];
                                            ?>
                                                <tr>
                                                    <td class="fw-medium text-secondary">
                                                        <?= htmlspecialchars($fee['fee_name']) ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $badge_color = 'bg-secondary';
                                                        if ($fee['frequency'] === 'monthly') $badge_color = 'bg-primary';
                                                        if ($fee['frequency'] === 'yearly') $badge_color = 'bg-success';
                                                        ?>
                                                        <span class="badge <?= $badge_color ?> badge-frequency">
                                                            <?= htmlspecialchars($fee['frequency']) ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-end fw-bold text-dark">
                                                        Rs. <?= number_format($fee['amount'], 2) ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endforeach; ?>

                        </div>
                    </div>
                <?php endforeach; ?>

            <?php endif; ?>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>