<?php
session_start();
require_once '../../backend/auth.php';
require_once '../../backend/config.php';

// Access Control Protection Layer
requireLogin();
if (isset($_SESSION['role']) && $_SESSION['role'] !== 'admin' && !in_array('timetable_manage', $_SESSION['permissions'] ?? [])) {
    header("Location: ../dashboard.php");
    exit();
}

// Target active operational Academic Year configuration context
$yearStmt = $pdo->query("SELECT id, year_name FROM academic_years ORDER BY is_current DESC, id DESC LIMIT 1");
$currentYear = $yearStmt->fetch();
$academic_year_id = $currentYear ? $currentYear['id'] : 1;

$message = "";

// Processing Workload Map Deletion Pipeline
if (isset($_GET['delete_id'])) {
    try {
        $delId = filter_var($_GET['delete_id'], FILTER_VALIDATE_INT);
        $delStmt = $pdo->prepare("DELETE FROM teacher_assignments WHERE id = ? AND academic_year_id = ?");
        $delStmt->execute([$delId, $academic_year_id]);
        
        $message = "
        <div class='alert alert-success alert-dismissible fade show border-0 shadow-sm d-flex align-items-center' role='alert'>
            <i class='bi bi-trash3-fill me-3 fs-4 text-success'></i>
            <div><strong>Mapping Terminated:</strong> Assignment removed safely from active engine buffer pipelines.</div>
            <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
        </div>";
    } catch (\Exception $e) {
        $message = "<div class='alert alert-danger border-0 shadow-sm'>Execution failure: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// Processing Assignment Submission Pipeline
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_assign_subject'])) {
    $teacher_id = filter_var($_POST['teacher_id'], FILTER_VALIDATE_INT);
    $course_id  = filter_var($_POST['course_id'], FILTER_VALIDATE_INT);
    $section_id = filter_var($_POST['section_id'], FILTER_VALIDATE_INT);

    if (!$teacher_id || !$course_id || !$section_id) {
        $message = "
        <div class='alert alert-warning alert-dismissible fade show border-0 shadow-sm d-flex align-items-center' role='alert'>
            <i class='bi bi-exclamation-triangle-fill me-3 fs-4 text-warning'></i>
            <div><strong>Validation Bound Fault:</strong> Ensure all core framework dropdown paths are selected.</div>
            <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
        </div>";
    } else {
        try {
            // Guardrail constraint: Prevent duplicates for the exact same course allocation in a section
            $dupCheck = $pdo->prepare("SELECT id FROM teacher_assignments WHERE course_id = ? AND section_id = ? AND academic_year_id = ? LIMIT 1");
            $dupCheck->execute([$course_id, $section_id, $academic_year_id]);
            
            if ($dupCheck->fetch()) {
                $message = "
                <div class='alert alert-danger alert-dismissible fade show border-0 shadow-sm d-flex align-items-center' role='alert'>
                    <i class='bi bi-shield-slash-fill me-3 fs-4 text-danger'></i>
                    <div><strong>Mapping Clashing:</strong> This course module has already been allocated to a different instructor profile for this section.</div>
                    <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                </div>";
            } else {
                $insStmt = $pdo->prepare("INSERT INTO teacher_assignments (teacher_id, course_id, section_id, academic_year_id) VALUES (?, ?, ?, ?)");
                $insStmt->execute([$teacher_id, $course_id, $section_id, $academic_year_id]);
                
                $message = "
                <div class='alert alert-success alert-dismissible fade show border-0 shadow-sm d-flex align-items-center' role='alert'>
                    <i class='bi bi-check-circle-fill me-3 fs-4 text-success'></i>
                    <div><strong>Workload Connected:</strong> The instructor is now bound to the specified domain layout segment.</div>
                    <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                </div>";
            }
        } catch (\Exception $e) {
            $message = "<div class='alert alert-danger border-0 shadow-sm'>Database Exception Fault: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}

// Query parameters data for contextual setup selectors
$teachersList = $pdo->query("SELECT t.teacher_id, u.full_name, t.employee_id FROM teachers t JOIN users u ON t.user_id = u.id ORDER BY u.full_name ASC")->fetchAll();
$coursesList  = $pdo->query("SELECT id, course_code, course_name FROM courses ORDER BY course_name ASC")->fetchAll();
$sectionsList = $pdo->query("SELECT s.id, CONCAT(c.class_name, ' - ', s.section_name) as title FROM sections s JOIN classes c ON s.class_id = c.id ORDER BY c.class_name ASC, s.section_name ASC")->fetchAll();

// Fetch structural current list assignments mapped
$assignments = $pdo->prepare("
    SELECT ta.id, u.full_name as teacher_name, t.employee_id, co.course_name, co.course_code, CONCAT(c.class_name, ' - ', s.section_name) as section_title
    FROM teacher_assignments ta
    JOIN teachers t ON ta.teacher_id = t.teacher_id
    JOIN users u ON t.user_id = u.id
    JOIN courses co ON ta.course_id = co.id
    JOIN sections s ON ta.section_id = s.id
    JOIN classes c ON s.class_id = c.id
    WHERE ta.academic_year_id = ?
    ORDER BY section_title ASC, co.course_name ASC
");
$assignments->execute([$academic_year_id]);
$activeAssignments = $assignments->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SCA Timetable Config - Assign Workload Maps</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="../../assets/css/sidebar.css" />
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <style>
        :root {
            --sca-primary: #2c3e50;
            --sca-bg: #f8f9fa;
        }

        body {
            background-color: var(--sca-bg);
            font-family: 'Poppins', sans-serif;
        }

        .main-content {
            margin-left: 260px;
            padding: 2rem;
            min-height: 100vh;
        }

        .page-header-card {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: #fff;
            border: none;
            border-radius: 12px;
        }

        .ui-card {
            background: #fff;
            border-radius: 12px;
            border: none;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        }

        .table-header th {
            background-color: var(--sca-primary) !important;
            color: #fff !important;
            font-weight: 500;
        }

        @media (max-width: 992px) {
            .main-content { margin-left: 0; padding: 1rem; }
        }
    </style>
</head>

<body>
    <!-- Sidebar component layout injection layer -->
    <?php include '../../backend/sidebar.php'; ?>

    <main class="main-content">
        <!-- Top Control Status Panel Desk Area -->
        <div class="card page-header-card shadow-sm mb-4">
            <div class="card-body p-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div>
                    <h3 class="m-0 fw-bold d-flex align-items-center gap-2">
                        <i class="bi bi-person-workspace text-info"></i> Teacher Assignment Matrix
                    </h3>
                    <small class="opacity-75">Target Session Frame: <strong class="text-warning"><?= htmlspecialchars($currentYear['year_name'] ?? 'Default Term Layer') ?></strong></small>
                </div>
                <div>
                    <a href="timetable_create.php" class="btn btn-info text-white fw-semibold px-4 py-2 shadow-sm d-flex align-items-center gap-2">
                        <i class="bi bi-arrow-right-circle-fill"></i> Go to Automation Desk
                    </a>
                </div>
            </div>
        </div>

        <?= $message ?>

        <div class="row g-4">
            <!-- Form Input Allocation Panel Component -->
            <div class="col-xl-4">
                <div class="card ui-card p-4">
                    <h5 class="fw-bold text-dark border-bottom pb-2 mb-3">
                        <i class="bi bi-link-45deg text-primary me-1"></i> Map Instructor Workload
                    </h5>
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Target Section Workspace</label>
                            <select name="section_id" class="form-select border-2" required style="border-radius: 8px;">
                                <option value="" disabled selected>-- Select Target Section --</option>
                                <?php foreach ($sectionsList as $sec): ?>
                                    <option value="<?= $sec['id'] ?>"><?= htmlspecialchars($sec['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Course Module Domain</label>
                            <select name="course_id" class="form-select border-2" required style="border-radius: 8px;">
                                <option value="" disabled selected>-- Select Course --</option>
                                <?php foreach ($coursesList as $crs): ?>
                                    <option value="<?= $crs['id'] ?>">[<?= htmlspecialchars($crs['course_code']) ?>] <?= htmlspecialchars($crs['course_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Lead Teacher / Instructor</label>
                            <select name="teacher_id" class="form-select border-2" required style="border-radius: 8px;">
                                <option value="" disabled selected>-- Select Instructor --</option>
                                <?php foreach ($teachersList as $tch): ?>
                                    <option value="<?= $tch['teacher_id'] ?>"><?= htmlspecialchars($tch['full_name']) ?> (<?= htmlspecialchars($tch['employee_id']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" name="action_assign_subject" class="btn btn-primary w-100 fw-semibold py-2 shadow-sm" style="border-radius: 8px;">
                            <i class="bi bi-plus-circle me-1"></i> Confirm Distribution Load
                        </button>
                    </form>
                </div>
            </div>

            <!-- Existing Mapped Entries View Screen -->
            <div class="col-xl-8">
                <div class="card ui-card p-4">
                    <h5 class="fw-bold text-dark border-bottom pb-2 mb-3">
                        <i class="bi bi-hdd-network text-secondary me-1"></i> Active Pipeline Mapping Registry
                    </h5>
                    
                    <div class="table-responsive rounded border">
                        <table class="table table-hover align-middle text-center m-0">
                            <thead class="table-header">
                                <tr>
                                    <th>Section Context</th>
                                    <th class="text-start">Course Allocation Module</th>
                                    <th class="text-start">Instructor Assigned</th>
                                    <th>Operation</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($activeAssignments)): ?>
                                    <tr>
                                        <td colspan="4" class="text-muted py-4 font-italic">No assignments bound for this academic cycle map frame. Add mappings on the left panel to trigger the timeline generator.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($activeAssignments as $row): ?>
                                        <tr>
                                            <td class="fw-bold text-dark text-nowrap"><i class="bi bi-building me-1 text-muted"></i> <?= htmlspecialchars($row['section_title']) ?></td>
                                            <td class="text-start">
                                                <span class="d-block fw-semibold text-primary"><?= htmlspecialchars($row['course_name']) ?></span>
                                                <small class="text-muted font-monospace" style="font-size:11px;"><?= htmlspecialchars($row['course_code']) ?></small>
                                            </td>
                                            <td class="text-start">
                                                <span class="d-block fw-medium text-dark"><i class="bi bi-person me-1"></i> <?= htmlspecialchars($row['teacher_name']) ?></span>
                                                <span class="badge bg-light text-secondary border font-monospace" style="font-size:10px;"><?= htmlspecialchars($row['employee_id']) ?></span>
                                            </td>
                                            <td>
                                                <a href="?delete_id=<?= $row['id'] ?>" class="btn btn-outline-danger btn-sm px-3 rounded-pill" onclick="return confirm('Are you sure you want to terminate this operational assignment mapping layout?');">
                                                    <i class="bi bi-trash"></i> Drop
                                                </a>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>