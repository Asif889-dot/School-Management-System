<?php

/**
 * Exam Terms Management Module - Smart Choice Academy
 */

// 1. Authentication, Configurations & Permission Guard
require_once '../../backend/auth.php';
require_once '../../backend/config.php'; // Session is initialized safely here

// Ensure login check helper executes safely
if (function_exists('requireLogin')) {
    requireLogin();
} else {
    // Fallback if the session hasn't been validated by your auth layer yet
    if (!isset($_SESSION['role'])) {
        header('Location: ../../backend/logout.php');
        exit;
    }
}

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && !in_array('exams_manage', $_SESSION['permissions'] ?? []))) {
    header('Location: dashboard.php');
    exit;
}

// 2. Database Connection Wrapper Linkage
// Maps perfectly with your config.php initialized instance
$db = $pdo ?? null;

if (!$db instanceof PDO) {
    die("Database Connection Error: Global database configuration variable \$pdo not initialized.");
}

// 3. Generate CSRF Token for Secure Post Forms
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$messageType = '';

// 4. Handle POST actions (Create, Update, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF Token validation failed.");
    }

    // ACTION: Add New Exam Term
    if (isset($_POST['action']) && $_POST['action'] === 'create') {
        $term_name = trim($_POST['term_name'] ?? '');
        $session_year = trim($_POST['session_year'] ?? '');
        $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        $status = $_POST['status'] === 'inactive' ? 'inactive' : 'active';

        if (!empty($term_name) && !empty($session_year)) {
            $stmt = $db->prepare("INSERT INTO exam_terms (term_name, session_year, start_date, end_date, status) VALUES (?, ?, ?, ?, ?)");
            if ($stmt->execute([$term_name, $session_year, $start_date, $end_date, $status])) {
                $message = "Exam Term added successfully!";
                $messageType = "success";
            } else {
                $message = "Failed to add Exam Term.";
                $messageType = "danger";
            }
        } else {
            $message = "Please fill in all mandatory fields.";
            $messageType = "warning";
        }
    }

    // ACTION: Update Existing Exam Term
    if (isset($_POST['action']) && $_POST['action'] === 'update') {
        $id = (int)($_POST['term_id'] ?? 0);
        $term_name = trim($_POST['term_name'] ?? '');
        $session_year = trim($_POST['session_year'] ?? '');
        $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        $status = $_POST['status'] === 'inactive' ? 'inactive' : 'active';

        if ($id > 0 && !empty($term_name) && !empty($session_year)) {
            $stmt = $db->prepare("UPDATE exam_terms SET term_name = ?, session_year = ?, start_date = ?, end_date = ?, status = ? WHERE id = ?");
            if ($stmt->execute([$term_name, $session_year, $start_date, $end_date, $status, $id])) {
                $message = "Exam Term configurations updated!";
                $messageType = "success";
            } else {
                $message = "Failed to update target Exam Term.";
                $messageType = "danger";
            }
        }
    }

    // ACTION: Delete Exam Term
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $id = (int)($_POST['term_id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->prepare("DELETE FROM exam_terms WHERE id = ?");
            if ($stmt->execute([$id])) {
                $message = "Exam Term deleted successfully.";
                $messageType = "success";
            } else {
                $message = "Unable to complete processing request.";
                $messageType = "danger";
            }
        }
    }
}

// 5. Fetch all Terms to display in Matrix table view
$stmt = $db->query("SELECT * FROM exam_terms ORDER BY session_year DESC, id DESC");
$examTerms = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Terms Matrix - Smart Choice Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <style>
        body {
            display: flex;
            min-height: 100vh;
            background-color: #f8f9fa;
            overflow-x: hidden;
        }

        /* Fixed structural target class from .main-wrapper to .main-content */
        .main-content {
            flex: 1;
            padding: 25px;
            transition: all 0.3s ease;
            min-width: 0;
            /* Prevents overflow elements from breaking flex containers */
        }

        .card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
        }

        .table th {
            background-color: #f1f3f5;
            font-weight: 600;
            color: #495057;
        }
    </style>
</head>

<body>

    <?php include '../../backend/sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-0 fw-bold text-dark">Exam Terms Matrix</h2>
                    <small class="text-muted">Configure academic structural assessment terms & evaluation periods</small>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTermModal">
                    <i class="bi bi-plus-circle me-2"></i>Create New Term
                </button>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                    <i class="bi <?= $messageType === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' ?> me-2"></i>
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4">Term ID</th>
                                    <th>Term Title Name</th>
                                    <th>Academic Session</th>
                                    <th>Duration Window</th>
                                    <th>Operational Status</th>
                                    <th class="text-end pe-4">Action Matrices</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($examTerms)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <i class="bi bi-folder-x display-6"></i>
                                            <p class="mt-2 mb-0">No structural evaluation periods mapped yet.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($examTerms as $term): ?>
                                        <tr>
                                            <td class="ps-4 fw-semibold text-secondary">#<?= $term['id'] ?></td>
                                            <td><span class="fw-bold text-dark"><?= htmlspecialchars($term['term_name']) ?></span></td>
                                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($term['session_year']) ?></span></td>
                                            <td>
                                                <small class="text-muted">
                                                    <i class="bi bi-calendar-event me-1"></i>
                                                    <?= $term['start_date'] ? date('M d, Y', strtotime($term['start_date'])) : 'N/A' ?>
                                                    <strong>→</strong>
                                                    <?= $term['end_date'] ? date('M d, Y', strtotime($term['end_date'])) : 'N/A' ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?php if ($term['status'] === 'active'): ?>
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1.5 rounded-pill">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2.5 py-1.5 rounded-pill">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end pe-4">
                                                <button class="btn btn-sm btn-outline-secondary edit-term-btn"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editTermModal"
                                                    data-id="<?= $term['id'] ?>"
                                                    data-name="<?= htmlspecialchars($term['term_name']) ?>"
                                                    data-session="<?= htmlspecialchars($term['session_year']) ?>"
                                                    data-start="<?= $term['start_date'] ?>"
                                                    data-end="<?= $term['end_date'] ?>"
                                                    data-status="<?= $term['status'] ?>">
                                                    <i class="bi bi-pencil-square"></i> Edit
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger delete-term-btn"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#deleteTermModal"
                                                    data-id="<?= $term['id'] ?>"
                                                    data-name="<?= htmlspecialchars($term['term_name']) ?>">
                                                    <i class="bi bi-trash3"></i>
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
    </main>

    <div class="modal fade" id="addTermModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form action="" method="POST" class="modal-content">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Create Academic Assessment Term</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Term Name <span class="text-danger">*</span></label>
                        <input type="text" name="term_name" class="form-control" placeholder="e.g., First Term" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Academic Session Year <span class="text-danger">*</span></label>
                        <input type="text" name="session_year" class="form-control" placeholder="e.g., 2026" required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Term Start Date</label>
                            <input type="date" name="start_date" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Term End Date</label>
                            <input type="date" name="end_date" class="form-control">
                        </div>
                    </div>
                    <div>
                        <label class="form-label fw-semibold">Operational Status</label>
                        <select name="status" class="form-select">
                            <option value="active" selected>Active / Evaluational</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Term Structure</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="editTermModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form action="" method="POST" class="modal-content">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="term_id" id="edit_term_id">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Update Term Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Term Name <span class="text-danger">*</span></label>
                        <input type="text" name="term_name" id="edit_term_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Academic Session Year <span class="text-danger">*</span></label>
                        <input type="text" name="session_year" id="edit_session_year" class="form-control" required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Term Start Date</label>
                            <input type="date" name="start_date" id="edit_start_date" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Term End Date</label>
                            <input type="date" name="end_date" id="edit_end_date" class="form-control">
                        </div>
                    </div>
                    <div>
                        <label class="form-label fw-semibold">Operational Status</label>
                        <select name="status" id="edit_status" class="form-select">
                            <option value="active">Active / Evaluational</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Update Configurations</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="deleteTermModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <form action="" method="POST" class="modal-content">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="term_id" id="delete_term_id">
                <div class="modal-body text-center pt-4">
                    <i class="bi bi-exclamation-octagon text-danger display-4 mb-2 d-inline-block"></i>
                    <h5 class="fw-bold">Confirm Deletion</h5>
                    <p class="text-muted small">Are you sure you want to completely clear out <strong id="delete_term_label" class="text-dark"></strong> matrix data? This cannot be undone.</p>
                </div>
                <div class="modal-footer border-top-0 d-flex justify-content-between pt-0">
                    <button type="button" class="btn w-50 btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn w-50 btn-danger btn-sm">Confirm Delete</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Setup dataset binders for Modals
            const editButtons = document.querySelectorAll('.edit-term-btn');
            editButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    document.getElementById('edit_term_id').value = this.dataset.id;
                    document.getElementById('edit_term_name').value = this.dataset.name;
                    document.getElementById('edit_session_year').value = this.dataset.session;
                    document.getElementById('edit_start_date').value = this.dataset.start;
                    document.getElementById('edit_end_date').value = this.dataset.end;
                    document.getElementById('edit_status').value = this.dataset.status;
                });
            });

            const deleteButtons = document.querySelectorAll('.delete-term-btn');
            deleteButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    document.getElementById('delete_term_id').value = this.dataset.id;
                    document.getElementById('delete_term_label').textContent = this.dataset.name;
                });
            });

            // Prevent Form Resubmission Prompt on Browser Refresh
            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
            }
        });
    </script>
</body>

</html>