<?php
require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();

$error_msg = "";
$search_query = "";
$params = [];

// Handle Search Query Filter
if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search_query = trim($_GET['search']);
    $sql = "SELECT b.id, b.book_title, b.author, b.created_at, c.class_name 
            FROM books b 
            LEFT JOIN classes c ON b.class_id = c.id 
            WHERE b.book_title LIKE :search OR b.author LIKE :search OR c.class_name LIKE :search
            ORDER BY c.class_name ASC, b.book_title ASC";
    $params = [':search' => '%' . $search_query . '%'];
} else {
    // Default Fetch Aligned with Updated Schema
    $sql = "SELECT b.id, b.book_title, b.author, b.created_at, c.class_name 
            FROM books b 
            LEFT JOIN classes c ON b.class_id = c.id 
            ORDER BY c.class_name ASC, b.book_title ASC";
}

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $books = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_msg = "Inventory Extraction Failure: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Book Inventory | Smart Choice Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <style>
        body {
            background-color: #f0f2f5;
            font-family: 'Inter', sans-serif;
            display: flex;
        }

        .main-content {
            margin-left: 260px;
            padding: 30px;
            width: 100%;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .table-responsive {
            border-radius: 12px;
            overflow: hidden;
        }

        .table thead {
            background-color: #f8f9fa;
        }

        .table th {
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #495057;
            padding: 15px;
        }

        .table td {
            padding: 15px;
            vertical-align: middle;
        }

        .badge-class {
            background-color: #e0e7ff;
            color: #4f46e5;
            font-weight: 600;
            font-size: 0.85rem;
            padding: 6px 12px;
            border-radius: 8px;
        }

        .btn-search {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
        }

        .btn-search:hover {
            background: linear-gradient(135deg, #5a73e8 0%, #6c3fa1 100%);
            color: white;
        }
    </style>
</head>

<body>

    <?php include '../../backend/sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid py-4">

            <div class="row align-items-center mb-4">
                <div class="col-md-8">
                    <h2 class="fw-bold text-dark mb-1">Book Inventory</h2>
                    <p class="text-muted">Monitor available academic textbooks and track their current class distributions.</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <a href="add_book.php" class="btn btn-primary px-4 py-2 shadow-sm border-0 style-gradient" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <i class="bi bi-plus-circle me-2"></i> Add New Book
                    </a>
                </div>
            </div>

            <?php if (isset($_GET['status']) && $_GET['status'] === 'deleted'): ?>
                <div class="alert alert-success alert-dismissible fade show card p-3 mb-4 border-start border-5 border-success" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-trash3-fill fs-4 me-3 text-success"></i>
                        <div>The textbook entry was removed from the system registry successfully.</div>
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

            <div class="card p-4 mb-4 bg-white">
                <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="GET" class="row g-3">
                    <div class="col-md-9">
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control bg-light" placeholder="Search by book name, author, or class name..." value="<?= htmlspecialchars($search_query) ?>">
                        </div>
                    </div>
                    <div class="col-md-3 d-grid">
                        <button type="submit" class="btn btn-search py-2 fw-semibold">
                            Filter Matrix
                        </button>
                    </div>
                </form>
            </div>

            <div class="card bg-white p-0 overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width: 80px;" class="text-center">SR #</th>
                                <th>Book Title / Name</th>
                                <th>Author / Publisher</th>
                                <th>Assigned Class Context</th>
                                <th style="width: 150px;" class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($books)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">
                                        <i class="bi bi-journal-x fs-1 d-block mb-3 text-secondary"></i>
                                        No structural textbook records found matching your current dataset views.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php $sr = 1; foreach ($books as $book): ?>
                                    <tr>
                                        <td class="text-center fw-bold text-secondary"><?= $sr++ ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="p-2 bg-light text-primary rounded-3 me-3">
                                                    <i class="bi bi-book fs-5"></i>
                                                </div>
                                                <div>
                                                    <span class="fw-bold text-dark d-block"><?= htmlspecialchars($book['book_title']) ?></span>
                                                    <small class="text-muted" style="font-size: 0.75rem;">Registered: <?= date('d-M-Y', strtotime($book['created_at'])) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-secondary font-medium">
                                            <?= !empty($book['author']) ? htmlspecialchars($book['author']) : '<em class="text-muted">Not Specified</em>' ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($book['class_name'])): ?>
                                                <span class="badge badge-class">
                                                    <i class="bi bi-mortarboard-fill me-1"></i> <?= htmlspecialchars($book['class_name']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary-subtle text-secondary fw-semibold font-sm rounded-3">Unassigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group" role="group">
                                                <a href="../../backend/edit_book.php?id=<?= $book['id'] ?>" class="btn btn-sm btn-outline-primary border-0 rounded-2" title="Edit Allocation Data">
                                                    <i class="bi bi-pencil-square fs-5"></i>
                                                </a>
                                                <a href="../../backend/delete_book.php?id=<?= $book['id'] ?>" class="btn btn-sm btn-outline-danger border-0 rounded-2" onclick="return confirm('Are you sure you want to remove this book allocation record?')" title="Delete Record">
                                                    <i class="bi bi-trash3 fs-5"></i>
                                                </a>
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