<?php
require_once 'auth.php';
require_once 'config.php';
requireLogin();

$success_msg = "";
$error_msg = "";

// 1. Fetch Existing Book Record on Page Load
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $book_id = intval($_GET['id']);
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM books WHERE id = :id");
        $stmt->execute([':id' => $book_id]);
        $book = $stmt->fetch();
        
        if (!$book) {
            header("Location: ../frontend/admin/books.php");
            exit;
        }
        
        // Populate initial variables for form
        $book_title = $book['book_title'];
        $author     = $book['author'];
        $class_id   = $book['class_id'];
    } catch (PDOException $e) {
        $error_msg = "Error pulling database record: " . $e->getMessage();
    }
} else if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // If no ID is passed and it's not a form submission, redirect back
    header("Location: ../frontend/admin/books.php");
    exit;
}

// 2. Handle Form Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $book_id     = intval($_POST['book_id'] ?? 0);
    $book_title  = trim($_POST['book_title'] ?? '');
    $author      = trim($_POST['author'] ?? '');
    $class_id    = !empty($_POST['class_id']) ? intval($_POST['class_id']) : null;

    if (empty($book_title)) {
        $error_msg = "Book Name is required.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE books 
                                   SET book_title = :book_title, author = :author, class_id = :class_id, updated_at = NOW() 
                                   WHERE id = :id");
            
            $stmt->execute([
                ':book_title' => $book_title,
                ':author'     => $author,
                ':class_id'   => $class_id,
                ':id'         => $book_id
            ]);

            $success_msg = "Book records updated and saved successfully!";
        } catch (PDOException $e) {
            $error_msg = "Failed to update record: " . $e->getMessage();
        }
    }
}

// 3. Fetch Classes for Dropdown Component Matrix
try {
    $classes = $pdo->query("SELECT id, class_name FROM classes ORDER BY class_name ASC")->fetchAll();
} catch (PDOException $e) {
    $error_msg = "Error loading classes: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Edit Book | Smart Choice Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
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

        .form-label {
            font-weight: 600;
            font-size: 0.85rem;
            color: #495057;
            letter-spacing: 0.5px;
        }

        .input-group-text {
            background-color: #f8f9fa;
            color: #6c757d;
        }

        .btn-update {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-update:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(118, 75, 162, 0.3);
            color: white;
        }
    </style>
</head>

<body>

    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid py-4">

            <div class="row align-items-center mb-4">
                <div class="col-md-8">
                    <h2 class="fw-bold text-dark mb-1">Modify Book Allocation</h2>
                    <p class="text-muted">Update catalog details or shift textbook dependencies to another class context level.</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <a href="../frontend/admin/books.php" class="btn btn-outline-secondary shadow-sm">
                        <i class="bi bi-arrow-left me-1"></i> Back to Inventory
                    </a>
                </div>
            </div>

            <!-- Operational Alerts -->
            <?php if (!empty($success_msg)): ?>
                <div class="alert alert-success alert-dismissible fade show card p-3 mb-4 border-start border-5 border-success" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-check-circle-fill fs-4 me-3 text-success"></i>
                        <div><?= $success_msg ?></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger alert-dismissible fade show card p-3 mb-4 border-start border-5 border-danger" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle-fill fs-4 me-3 text-danger"></i>
                        <div><?= $error_msg ?></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Edit Allocation Form Form -->
            <div class="card p-4 p-md-5 bg-white">
                <h4 class="fw-bold mb-4 text-primary d-flex align-items-center">
                    <i class="bi bi-pencil-square me-2"></i> Update Specifications
                </h4>
                <hr class="text-muted mb-4">

                <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) . '?id=' . $book_id ?>" method="POST" autocomplete="off">
                    <!-- Hidden contextual primary ID field -->
                    <input type="hidden" name="book_id" value="<?= $book_id ?>">

                    <div class="row g-4">
                        
                        <!-- Book Title / Name -->
                        <div class="col-md-12">
                            <label class="form-label text-uppercase">Book Name / Title <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-journal-bookmark"></i></span>
                                <input type="text" name="book_title" class="form-control bg-light" placeholder="e.g. Mathematics Grade 10" value="<?= htmlspecialchars($book_title ?? '') ?>" required>
                            </div>
                        </div>

                        <!-- Book Author -->
                        <div class="col-md-6">
                            <label class="form-label text-uppercase">Book Author / Publisher</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input type="text" name="author" class="form-control bg-light" placeholder="e.g. Punjab Textbook Board" value="<?= htmlspecialchars($author ?? '') ?>">
                            </div>
                        </div>

                        <!-- Assign Class Selection Element Matrix -->
                        <div class="col-md-6">
                            <label class="form-label text-uppercase">Assign to Class <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-mortarboard"></i></span>
                                <select name="class_id" class="form-select bg-light" required>
                                    <option value="">Select Target Class...</option>
                                    <?php foreach ($classes as $c): ?>
                                        <option value="<?= $c['id'] ?>" <?= isset($class_id) && $class_id == $c['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($c['class_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Submission Panel Buttons -->
                        <div class="col-12 mt-5 text-end">
                            <a href="books.php" class="btn btn-light px-4 me-2">Cancel</a>
                            <button type="submit" class="btn btn-update px-5 py-2 shadow-sm">
                                <i class="bi bi-save me-2"></i> Save Modifications
                            </button>
                        </div>

                    </div>
                </form>
            </div>

        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>