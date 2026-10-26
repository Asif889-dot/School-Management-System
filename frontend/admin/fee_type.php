<?php
require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();
requireRole('admin');

// Handle Form Submission (Inline for simplicity, or move to backend file)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_type'])) {
    $name = trim(filter_input(INPUT_POST, 'fee_name', FILTER_SANITIZE_STRING));
    $desc = trim(filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING));

    if (!empty($name)) {
        $stmt = $pdo->prepare("INSERT INTO fee_types (name, description) VALUES (?, ?)");
        if ($stmt->execute([$name, $desc])) {
            $msg = "Fee Type added successfully!";
            $type = "success";
        }
    }
}

// Fetch all types
$feeTypes = $pdo->query("SELECT * FROM fee_types ORDER BY id DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Fee Types | SMS</title>
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
        }

        .form-header {
            background: var(--primary-gradient);
            color: white;
            padding: 20px;
            border-radius: 20px 20px 0 0;
        }

        .btn-submit {
            background: var(--primary-gradient);
            border: none;
            color: white;
            border-radius: 10px;
            padding: 10px 25px;
        }

        .table thead {
            background: #f8f9fa;
            color: var(--accent-purple);
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
            <h5 class="mb-0"><i class="bi bi-tags-fill me-2"></i>Fee Categories</h5>
        </header>

        <div class="dashboard-content container-fluid">
            <?php if (isset($msg)): ?>
                <div class="alert alert-<?= $type ?> alert-dismissible fade show"><?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>

            <div class="row g-4">
                <div class="col-md-4">
                    <div class="fee-card">
                        <div class="form-header text-center">
                            <h5 class="mb-0">Add New Fee Type</h5>
                        </div>
                        <form method="POST" class="p-4">
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Fee Name</label>
                                <input type="text" name="fee_name" class="form-control" placeholder="e.g. Admission Fee" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Description (Optional)</label>
                                <textarea name="description" class="form-control" rows="3" placeholder="Briefly describe this fee..."></textarea>
                            </div>
                            <button type="submit" name="add_type" class="btn btn-submit w-100 shadow-sm">Save Fee Type</button>
                        </form>
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="fee-card p-4">
                        <h5 class="fw-bold mb-4 text-muted">Existing Fee Types</h5>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Fee Name</th>
                                        <th>Description</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($feeTypes as $index => $ft): ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td class="fw-bold"><?= htmlspecialchars($ft['name']) ?></td>
                                            <td class="small text-muted"><?= htmlspecialchars($ft['description']) ?></td>
                                            <td class="text-center">
                                                <button class="btn btn-sm btn-outline-danger border-0" onclick="return confirm('Delete this fee type?')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>