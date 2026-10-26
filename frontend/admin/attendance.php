<?php
// Attendance Tracking Control Console - Smart Choice Academy
require_once '../../backend/auth.php';
requireLogin();
// Allows both administrators and teachers access to the log registry dashboard
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'teacher') {
    die("Access Denied: You do not possess the required credentials to access this system module.");
}

// Configuration link instantiation
require_once '../../backend/config.php';

// Capture filtering context configuration flags
$selected_class = $_GET['class_id'] ?? '';
$selected_section = $_GET['section_id'] ?? '';
$selected_date = $_GET['date'] ?? date('Y-m-d');

$students = [];
$attendance_saved = false;
$error_message = '';

try {
    // 1. FETCH ALL ACTIVE DESIGNATED CLASSES FOR DROPDOWNS
    $classesQuery = $pdo->query("SELECT id, class_name FROM classes ORDER BY numeric_grade ASC");
    $classes = $classesQuery->fetchAll(PDO::FETCH_ASSOC);

    // 2. IF A CLASS IS TARGETED, DYNAMICALLY EXTRACT ITS RELATED SECTIONS
    $sections = [];
    if (!empty($selected_class)) {
        $sectionsStmt = $pdo->prepare("SELECT id, section_name FROM sections WHERE class_id = ? ORDER BY section_name ASC");
        $sectionsStmt->execute([$selected_class]);
        $sections = $sectionsStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 3. IF FILTERS ARE APPLIED, INJECT THE SYSTEM REGISTER LIST
    if (!empty($selected_class) && !empty($selected_section)) {
        // Fetch matching approved student rows
        $studentsStmt = $pdo->prepare("
            SELECT s.id, s.reg_id, s.first_name, s.last_name, s.roll_no
            FROM students s
            WHERE s.class_id = ? AND s.section_id = ? AND s.status = 'approved'
            ORDER BY s.roll_no ASC, s.first_name ASC
        ");
        $studentsStmt->execute([$selected_class, $selected_section]);
        $students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);

        // Map already recorded attendance items for the chosen date configuration rule
        $existingStmt = $pdo->prepare("
            SELECT student_id, status FROM attendance 
            WHERE class_id = ? AND section_id = ? AND attendance_date = ?
        ");
        $existingStmt->execute([$selected_class, $selected_section, $selected_date]);
        $existing_records = $existingStmt->fetchAll(PDO::FETCH_KEY_PAIR); // Generates array map of [student_id => status]
    }

    // 4. BATCH PERSISTENCE FORM HANDLING MATRIX
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
        $posted_attendance = $_POST['attendance'] ?? []; // Map containing [student_id => status_string]
        
        if (!empty($students)) {
            $pdo->beginTransaction();

            // Prepare baseline core query templates to execution pipelines
            $deleteStmt = $pdo->prepare("DELETE FROM attendance WHERE class_id = ? AND section_id = ? AND attendance_date = ?");
            $deleteStmt->execute([$selected_class, $selected_section, $selected_date]);

            $insertStmt = $pdo->prepare("
                INSERT INTO attendance (student_id, class_id, section_id, attendance_date, status, marked_by) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            foreach ($students as $student) {
                // Default unflagged records down to 'present' state to protect operations speeds
                $status = $posted_attendance[$student['id']] ?? 'present';
                $insertStmt->execute([
                    $student['id'],
                    $selected_class,
                    $selected_section,
                    $selected_date,
                    $status,
                    $_SESSION['user_id'] ?? null
                ]);
            }

            $pdo->commit();
            $attendance_saved = true;
            
            // Refresh dynamic local lookups array state values instantly following execution
            $existing_records = $posted_attendance;
        } else {
            $error_message = "Failed submission. No operational student entities were loaded inside the workbook frame.";
        }
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Attendance Engine Exception: " . $e->getMessage());
    $error_message = "Critical runtime malfunction detected inside processing loops: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Attendance Console | Smart Choice Academy</title>
    
    <!-- Bootstrap Core framework -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Typography -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Component Icons Font layer -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- Application Platform Layout Layers -->
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>

<body>
    <!-- Sidebar Workspace Injected -->
    <?php include '../../backend/sidebar.php'; ?>

    <!-- Main Engine Context Container Frame -->
    <main class="main-content">
        <header class="top-header">
            <div class="d-flex align-items-center gap-2">
                <span class="fs-5 fw-medium text-dark"><i class="bi bi-calendar-check me-2"></i>Daily Attendance Management</span>
            </div>
            <div class="user-menu">
                <div class="text-end d-none d-md-block">
                    <div class="fw-semibold"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Authorized Operator'); ?></div>
                    <small class="text-muted text-capitalize"><?php echo htmlspecialchars($_SESSION['role']); ?></small>
                </div>
                <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['full_name'] ?? 'OP', 0, 2)); ?></div>
            </div>
        </header>

        <div class="dashboard-content">
            
            <!-- Context Alerts Panel Layout -->
            <?php if ($attendance_saved): ?>
                <div class="alert alert-success d-flex align-items-center role="alert">
                    <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                    <div>Attendance spreadsheet data logged and indexed successfully for the selected index date target.</div>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger d-flex align-items-center" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                    <div><?php echo htmlspecialchars($error_message); ?></div>
                </div>
            <?php endif; ?>

            <!-- Filtering Configuration Grid Engine Panel -->
            <div class="card border-0 shadow-sm rounded mb-4 bg-white">
                <div class="card-body p-4">
                    <form method="GET" action="attendance.php" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label small text-muted fw-medium">Target Academic Class/Group</label>
                            <select name="class_id" class="form-select" onchange="this.form.submit()" required>
                                <option value="">-- Choose Class Group --</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['id']; ?>" <?php echo ($selected_class == $class['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($class['class_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small text-muted fw-medium">Assigned Sub-Section</label>
                            <select name="section_id" class="form-select" ?php echo empty($selected_class) ? 'disabled' : ''; ?> onchange="this.form.submit()" required>
                                <option value="">-- Choose Section --</option>
                                <?php foreach ($sections as $sec): ?>
                                    <option value="<?php echo $sec['id']; ?>" <?php echo ($selected_section == $sec['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($sec['section_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small text-muted fw-medium">Log Registry Target Date</label>
                            <input type="date" name="date" class="form-select" value="<?php echo htmlspecialchars($selected_date); ?>" max="<?php echo date('Y-m-d'); ?>" onchange="this.form.submit()">
                        </div>

                        <div class="col-md-3">
                            <a href="attendance.php" class="btn btn-light border w-100"><i class="bi bi-arrow-counterclockwise me-1"></i> Clear Selection</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Operational System Workspace Tracking Workbook Sheet Table -->
            <?php if (!empty($selected_class) && !empty($selected_section)): ?>
                <form method="POST" action="attendance.php?class_id=<?php echo urlencode($selected_class); ?>&section_id=<?php echo urlencode($selected_section); ?>&date=<?php echo urlencode($selected_date); ?>">
                    <div class="content-card bg-white shadow-sm rounded overflow-hidden">
                        <div class="card-header d-flex justify-content-between align-items-center p-3 bg-light border-bottom">
                            <h5 class="card-title m-0 fw-semibold text-dark">
                                <i class="bi bi-journal-text me-2"></i>Roll Call Registry Grid
                            </h5>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-outline-success" onclick="toggleAllAttendance('present')">All Present</button>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="toggleAllAttendance('absent')">All Absent</button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <table class="data-table table m-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 10%;">Roll No.</th>
                                        <th style="width: 15%;">Reg. ID</th>
                                        <th style="width: 40%;">Student Full Name</th>
                                        <th class="text-center" style="width: 35%;">Attendance Allocation Status Flags</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($students) > 0): ?>
                                        <?php foreach ($students as $student): ?>
                                            <?php 
                                                // Identify historical registry tracking values to override defaults
                                                $current_status = $existing_records[$student['id']] ?? 'present'; 
                                            ?>
                                            <tr>
                                                <td><span class="fw-bold text-dark"><?php echo htmlspecialchars($student['roll_no'] ?: '-'); ?></span></td>
                                                <td><code class="small text-secondary"><?php echo htmlspecialchars($student['reg_id']); ?></code></td>
                                                <td>
                                                    <div class="fw-semibold text-dark">
                                                        <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <!-- Segmented Radio Group Styling Components Block -->
                                                    <div class="btn-group w-100" role="group" style="max-width: 300px;">
                                                        <input type="radio" class="btn-check" name="attendance[<?php echo $student['id']; ?>]" id="p_<?php echo $student['id']; ?>" value="present" <?php echo ($current_status === 'present') ? 'checked' : ''; ?>>
                                                        <label class="btn btn-outline-success btn-sm py-1.5" for="p_<?php echo $student['id']; ?>">Present</label>

                                                        <input type="radio" class="btn-check" name="attendance[<?php echo $student['id']; ?>]" id="l_<?php echo $student['id']; ?>" value="leave" <?php echo ($current_status === 'leave') ? 'checked' : ''; ?>>
                                                        <label class="btn btn-outline-warning btn-sm py-1.5" for="l_<?php echo $student['id']; ?>">Leave</label>

                                                        <input type="radio" class="btn-check" name="attendance[<?php echo $student['id']; ?>]" id="a_<?php echo $student['id']; ?>" value="absent" <?php echo ($current_status === 'absent') ? 'checked' : ''; ?>>
                                                        <label class="btn btn-outline-danger btn-sm py-1.5" for="a_<?php echo $student['id']; ?>">Absent</label>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center p-5 text-muted">
                                                <i class="bi bi-people-fill fs-2 d-block mb-2 text-light-emphasis"></i>
                                                No approved operational data entities matching parameters found inside this workbook sequence structure.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if (count($students) > 0): ?>
                            <div class="card-footer p-3 bg-light border-top d-flex justify-content-end">
                                <button type="submit" name="save_attendance" class="btn btn-primary px-4 shadow-sm">
                                    <i class="bi bi-shield-fill-check me-1"></i> Save Daily Ledger Spreadsheet
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </form>
            <?php else: ?>
                <!-- Unselected Filter Target Canvas Frame -->
                <div class="text-center p-5 bg-white border rounded shadow-sm">
                    <i class="bi bi-funnel fs-1 text-muted opacity-50 mb-3 d-block"></i>
                    <h5 class="text-dark fw-medium">Execution Workflow Not Initialized</h5>
                    <p class="text-muted small max-width-400 mx-auto">Please isolate an operational Class Group and specific subsection column reference inside the query grid engine header fields wrapper block above to compile targeted roster sheets.</p>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <!-- Bootstrap Javascript Bundle Elements Bundle Injection -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Script Module to Toggle Document-wide Controls quickly -->
    <script>
        function toggleAllAttendance(statusTarget) {
            const targets = document.querySelectorAll(`input[value="${statusTarget}"]`);
            targets.forEach(radioInput => {
                radioInput.checked = true;
            });
        }
    </script>
</body>

</html>