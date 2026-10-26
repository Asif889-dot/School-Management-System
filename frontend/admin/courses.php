<?php
require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();

$error_msg = "";
$search_query = "";
$params = [];

// Handle Search Query Filter Matrix
if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search_query = trim($_GET['search']);
    $sql = "SELECT co.id, co.course_name, co.course_code, co.created_at, cl.class_name 
            FROM courses co 
            INNER JOIN classes cl ON co.class_id = cl.id 
            WHERE co.course_name LIKE :search OR co.course_code LIKE :search OR cl.class_name LIKE :search
            ORDER BY cl.class_name ASC, co.course_name ASC";
    $params = [':search' => '%' . $search_query . '%'];
} else {
    // Default Systematic Extraction Layout
    $sql = "SELECT co.id, co.course_name, co.course_code, co.created_at, cl.class_name 
            FROM courses co 
            INNER JOIN classes cl ON co.class_id = cl.id 
            ORDER BY cl.class_name ASC, co.course_name ASC";
}

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $courses = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_msg = "Syllabus Extraction Failure: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Course Inventory | Smart Choice Academy</title>
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

        .badge-code {
            background-color: #f3f4f6;
            color: #374151;
            font-weight: 500;
            font-size: 0.8rem;
            padding: 4px 8px;
            border-radius: 6px;
            font-family: 'Courier New', Courier, monospace;
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
                    <h2 class="fw-bold text-dark mb-1">Course Catalog Matrix</h2>
                    <p class="text-muted">Review, filter, and organize academic courses established across school classes.</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <a href="add_course.php" class="btn btn-primary px-4 py-2 shadow-sm border-0" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <i class="bi bi-plus-circle me-2"></i> Add New Course
                    </a>
                </div>
            </div>

            <?php if (isset($_GET['status']) && $_GET['status'] === 'deleted'): ?>
                <div class="alert alert-success alert-dismissible fade show card p-3 mb-4 border-start border-5 border-success" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-trash3-fill fs-4 me-3 text-success"></i>
                        <div>The requested course record has been dropped from the curriculum matrix successfully.</div>
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
                            <input type="text" name="search" class="form-control bg-light" placeholder="Search by course name, course code, or mapped class..." value="<?= htmlspecialchars($search_query) ?>">
                        </div>
                    </div>
                    <div class="col-md-3 d-grid">
                        <button type="submit" class="btn btn-search py-2 fw-semibold">
                            Filter Catalog
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
                                <th>Course Title / Descriptor</th>
                                <th>Systematic Code</th>
                                <th>Assigned Class Level Context</th>
                                <th style="width: 150px;" class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($courses)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">
                                        <i class="bi bi-folder-x fs-1 d-block mb-3 text-secondary"></i>
                                        No active courses or syllabus segments match your filtering boundaries.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php $sr = 1; foreach ($courses as $course): ?>
                                    <tr>
                                        <td class="text-center fw-bold text-secondary"><?= $sr++ ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="p-2 bg-light text-success rounded-3 me-3">
                                                    <i class="bi bi-collection-play fs-5"></i>
                                                </div>
                                                <div>
                                                    <span class="fw-bold text-dark d-block"><?= htmlspecialchars($course['course_name']) ?></span>
                                                    <small class="text-muted" style="font-size: 0.75rem;">Created: <?= date('d-M-Y', strtotime($course['created_at'])) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($course['course_code'])): ?>
                                                <span class="badge-code"><?= htmlspecialchars($course['course_code']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted small"><em>None Assigned</em></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-class">
                                                <i class="bi bi-mortarboard-fill me-1"></i> <?= htmlspecialchars($course['class_name']) ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group" role="group">
                                                <a href="edit_course.php?id=<?= $course['id'] ?>" class="btn btn-sm btn-outline-primary border-0 rounded-2" title="Edit Course Definition">
                                                    <i class="bi bi-pencil-square fs-5"></i>
                                                </a>
                                                <a href="delete_course.php?id=<?= $course['id'] ?>" class="btn btn-sm btn-outline-danger border-0 rounded-2" onclick="return confirm('Are you sure you want to drop this course assignment registration? This step cannot be undone.')" title="Delete Course">
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