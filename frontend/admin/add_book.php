<?php
require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();

$success_msg = "";
$error_msg = "";

// 1. Handle Form Submission securely
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $book_title  = trim($_POST['book_title'] ?? '');
    $author      = trim($_POST['author'] ?? '');
    $class_id    = !empty($_POST['class_id']) ? intval($_POST['class_id']) : null;

    if (empty($book_title)) {
        $error_msg = "Book Name is required.";
    } else {
        try {
            // Inserts into your books table with only the core requested fields
            $stmt = $pdo->prepare("INSERT INTO books (book_title, author, class_id, created_at) 
                                   VALUES (:book_title, :author, :class_id, NOW())");
            
            $stmt->execute([
                ':book_title' => $book_title,
                ':author'     => $author,
                ':class_id'   => $class_id
            ]);

            $success_msg = "Book '<strong>" . htmlspecialchars($book_title) . "</strong>' added and assigned to the class successfully!";
            
            // Clear inputs on successful save
            $book_title = $author = $class_id = "";
        } catch (PDOException $e) {
            $error_msg = "Database Error: " . $e->getMessage();
        }
    }
}

// 2. Fetch Classes for Selection Dropdown
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
    <title>Add Book | Smart Choice Academy</title>
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

        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(118, 75, 162, 0.3);
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
                    <h2 class="fw-bold text-dark mb-1">Add New Book</h2>
                    <p class="text-muted">Register textbooks and assign them directly to an academic class level.</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <a href="books.php" class="btn btn-outline-secondary shadow-sm">
                        <i class="bi bi-collection text-secondary me-1"></i> View Book Inventory
                    </a>
                </div>
            </div>

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

            <div class="card p-4 p-md-5 bg-white">
                <h4 class="fw-bold mb-4 text-primary d-flex align-items-center">
                    <i class="bi bi-book-half me-2"></i> Book Allocation Setup
                </h4>
                <hr class="text-muted mb-4">

                <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST" autocomplete="off">
                    <div class="row g-4">
                        
                        <div class="col-md-12">
                            <label class="form-label text-uppercase">Book Name / Title <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-journal-bookmark"></i></span>
                                <input type="text" name="book_title" class="form-control bg-light" placeholder="e.g. Mathematics Grade 10" value="<?= htmlspecialchars($book_title ?? '') ?>" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-uppercase">Book Author / Publisher</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input type="text" name="author" class="form-control bg-light" placeholder="e.g. Punjab Textbook Board" value="<?= htmlspecialchars($author ?? '') ?>">
                            </div>
                        </div>

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

                        <div class="col-12 mt-5 text-end">
                            <button type="reset" class="btn btn-light px-4 me-2">Clear</button>
                            <button type="submit" class="btn btn-submit px-5 py-2 shadow-sm">
                                <i class="bi bi-plus-circle me-2"></i> Save Book Assignment
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