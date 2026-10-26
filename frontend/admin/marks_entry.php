<?php

/**
 * Marks Entry Management Module - Smart Choice Academy
 * Optimized for Secure Dynamic Bulk Marks Entry & Spreadsheet-style Grid
 * Features Hierarchical Filters: Class -> Section -> Subject
 */

require_once '../../backend/auth.php';
require_once '../../backend/config.php';

if (function_exists('requireLogin')) {
    requireLogin();
} else {
    if (!isset($_SESSION['role'])) {
        header('Location: ../../backend/logout.php');
        exit;
    }
}

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && !in_array('exams_manage', $_SESSION['permissions'] ?? []))) {
    header('Location: dashboard.php');
    exit;
}

$db = $pdo ?? null;
if (!$db instanceof PDO) {
    die("Database Connection Error: Global database configuration variable \$pdo not initialized.");
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$messageType = '';

$selected_term_id = (int)($_GET['term_id'] ?? 0);
$selected_class_id = (int)($_GET['class_id'] ?? 0);
$selected_section_id = (int)($_GET['section_id'] ?? 0);
$selected_subject_name = trim($_GET['subject_name'] ?? '');

// 3. Handle POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_marks') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF Token validation failed.");
    }

    $term_id = (int)($_POST['term_id'] ?? 0);
    $class_id = (int)($_POST['class_id'] ?? 0);
    $section_id = (int)($_POST['section_id'] ?? 0);
    $subject_name = trim($_POST['subject_name'] ?? '');
    $total_marks = (float)($_POST['total_marks'] ?? 100);
    $passing_marks = (float)($_POST['passing_marks'] ?? 33);
    $obtained_marks_array = $_POST['obtained_marks'] ?? [];

    // Update the POST handler logic
    if ($term_id > 0 && $class_id > 0 && $section_id > 0 && !empty($subject_name)) {
        try {
            $db->beginTransaction();

            $stmt = $db->prepare("
            INSERT INTO exam_marks (term_id, class_id, section_id, subject_name, student_id, total_marks, passing_marks, obtained_marks, exam_date, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
                total_marks = VALUES(total_marks),
                passing_marks = VALUES(passing_marks),
                obtained_marks = VALUES(obtained_marks),
                exam_date = VALUES(exam_date),
                updated_at = NOW()
        ");

            $savedCount = 0;
            $exam_dates_array = $_POST['exam_dates'] ?? []; // Capture the new array

            foreach ($obtained_marks_array as $student_id => $obtained_val) {
                if ($obtained_val === '' && empty($exam_dates_array[$student_id])) continue;

                // Get date for this specific student
                $date = !empty($exam_dates_array[$student_id]) ? $exam_dates_array[$student_id] : null;

                $stmt->execute([
                    $term_id,
                    $class_id,
                    $section_id,
                    $subject_name,
                    (int)$student_id,
                    $total_marks,
                    $passing_marks,
                    (float)$obtained_val,
                    $date // Added 9th parameter
                ]);
                $savedCount++;
            }

            $db->commit();
            $message = "Successfully saved records and dates for {$savedCount} students!";
            $messageType = "success";
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $message = "Transaction Fault: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

// 4. Fetch Infrastructure
$terms = $db->query("SELECT id, term_name, session_year FROM exam_terms WHERE status = 'active' ORDER BY session_year DESC, id DESC")->fetchAll();
$classes = $db->query("SELECT id, class_name FROM classes ORDER BY class_name ASC")->fetchAll();

$students_list = [];
$configured_total_marks = 100;
$configured_passing_marks = 33;

if ($selected_term_id > 0 && $selected_class_id > 0 && $selected_section_id > 0 && !empty($selected_subject_name)) {
    // Fetch configuration
    $config_check = $db->prepare("SELECT total_marks, passing_marks FROM exam_marks WHERE term_id = ? AND class_id = ? AND section_id = ? AND subject_name = ? LIMIT 1");
    $config_check->execute([$selected_term_id, $selected_class_id, $selected_section_id, $selected_subject_name]);
    $existing_config = $config_check->fetch();
    if ($existing_config) {
        $configured_total_marks = (float)$existing_config['total_marks'];
        $configured_passing_marks = (float)$existing_config['passing_marks'];
    }

    // Fetch students
    // B. Fetch students with Positional Placeholders (?)
    // B. Fetch students and join with exam_datesheet to validate the exam date
    // B. Fetch students and join with the CORRECT table name: 'datesheet'
    // Updated query using the correct table name found via SHOW TABLES
    // Fetch students without the missing datesheet table
    $students_query = "
        SELECT s.id AS student_id, s.id AS roll_no, s.first_name, s.last_name, m.obtained_marks, m.exam_date
        FROM students s
        LEFT JOIN exam_marks m ON m.student_id = s.id 
               AND m.term_id = ? 
               AND m.subject_name = ?
               AND m.class_id = ?
               AND m.section_id = ?
        WHERE s.class_id = ? 
          AND s.section_id = ?
        ORDER BY s.id ASC
    ";

    $stmt_students = $db->prepare($students_query);

    // Provide only the 6 parameters required by the updated query
    $stmt_students->execute([
        $selected_term_id,     // m.term_id
        $selected_subject_name, // m.subject_name
        $selected_class_id,    // m.class_id
        $selected_section_id,  // m.section_id
        $selected_class_id,    // s.class_id
        $selected_section_id   // s.section_id
    ]);

    $students_list = $stmt_students->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Ledger (Marks Entry) - Smart Choice Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <style>
        body {
            display: flex;
            min-height: 100vh;
            background-color: #f8f9fa;
        }

        .main-content {
            flex: 1;
            padding: 25px;
            min-width: 0;
        }

        .card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.04);
        }

        .ledger-table th {
            background-color: #212529 !important;
            color: #ffffff !important;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            vertical-align: middle;
            border: 1px solid #2d3238;
        }

        .ledger-table td {
            vertical-align: middle;
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
        }

        .marks-input-field {
            max-width: 90px;
            font-weight: 700;
            text-align: center;
            border-radius: 4px;
            border: 1px solid #cbd5e1;
            transition: all 0.2s ease;
        }

        .marks-input-field:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
            background-color: #fef08a;
        }

        .status-badge {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 4px 8px;
            border-radius: 4px;
        }

        .status-pass {
            background-color: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .status-fail {
            background-color: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .status-pending {
            background-color: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        @media print {

            #sidebar,
            .sidebar,
            nav,
            .btn,
            .filter-section-card,
            .alert,
            .app-no-print {
                display: none !important;
            }

            body {
                background: #fff;
                display: block;
            }

            .main-content {
                padding: 0;
                margin: 0;
            }

            .ledger-table th {
                background-color: #000 !important;
                color: #fff !important;
                border: 1px solid #000 !important;
            }

            .ledger-table td {
                border: 1px solid #000 !important;
            }

            .marks-input-field {
                border: none !important;
                background: transparent !important;
                pointer-events: none;
            }
        }
    </style>
</head>

<body>
    <?php include '../../backend/sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid">

            <div class="d-flex justify-content-between align-items-center mb-4 app-no-print">
                <div>
                    <h2 class="mb-0 fw-bold text-dark">Examination Ledger</h2>
                    <small class="text-muted">Process dynamic student grade entries and raw score valuations</small>
                </div>
                <div>
                    <?php if (!empty($students_list)): ?>
                        <button class="btn btn-outline-dark me-2" onclick="window.print();">
                            <i class="bi bi-printer me-1"></i> Print Layout
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                    <i class="bi <?= $messageType === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' ?> me-2"></i>
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card filter-section-card mb-4">
                <div class="card-body bg-white p-4">
                    <form method="GET" action="" class="row g-3 align-items-end">
                        <div class="col-md-2">
                            <label class="form-label fw-bold small text-secondary">Evaluation Term</label>
                            <select name="term_id" class="form-select" required>
                                <option value="">-- Select Term --</option>
                                <?php foreach ($terms as $t): ?>
                                    <option value="<?= $t['id'] ?>" <?= $selected_term_id === (int)$t['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($t['term_name'] . ' [' . $t['session_year'] . ']') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-secondary">Target Class Boundary</label>
                            <select name="class_id" id="filter_class_select" class="form-select" required>
                                <option value="">-- Choose Class Group --</option>
                                <?php foreach ($classes as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= $selected_class_id === (int)$c['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['class_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold small text-secondary">Section Boundary</label>
                            <select name="section_id" id="filter_section_select" class="form-select" required>
                                <option value="">-- Choose Section --</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-secondary">Subject Course Title</label>
                            <select name="subject_name" id="filter_subject_select" class="form-select" required>
                                <option value="">-- Choose Subject --</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100 fw-bold">
                                <i class="bi bi-search me-2"></i>Load Roster
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="d-flex align-items-center mb-3">
                <input type="date" id="global_date" class="form-control form-control-sm w-auto me-2">
                <button type="button" class="btn btn-sm btn-primary" onclick="document.querySelectorAll('.exam-date-input').forEach(i => i.value = document.getElementById('global_date').value)">
                    Apply to All
                </button>
            </div>
            <?php if ($selected_term_id > 0 && $selected_class_id > 0 && $selected_section_id > 0 && !empty($selected_subject_name)): ?>
                <form action="" method="POST" id="ledger_entry_form">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="action" value="save_marks">
                    <input type="hidden" name="term_id" value="<?= $selected_term_id ?>">
                    <input type="hidden" name="class_id" value="<?= $selected_class_id ?>">
                    <input type="hidden" name="section_id" value="<?= $selected_section_id ?>">
                    <input type="hidden" name="subject_name" value="<?= htmlspecialchars($selected_subject_name) ?>">

                    <div class="card mb-3 bg-light border">
                        <div class="card-body py-3 row g-3 align-items-center">
                            <div class="col-auto">
                                <span class="fw-bold text-dark text-uppercase small tracking-wider">Evaluation Rules Profile:</span>
                            </div>
                            <div class="col-md-2">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-white fw-medium text-muted">Max Marks</span>
                                    <input type="number" step="0.5" name="total_marks" id="global_max_marks"
                                        class="form-control fw-bold text-center" value="<?= $configured_total_marks ?>" min="1" required>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-white fw-medium text-muted">Passing Cutoff</span>
                                    <input type="number" step="0.5" name="passing_marks" id="global_pass_marks"
                                        class="form-control fw-bold text-center" value="<?= $configured_passing_marks ?>" min="0" required>
                                </div>
                            </div>
                            <div class="col text-end app-no-print">
                                <small class="text-muted font-monospace"><i class="bi bi-info-circle me-1"></i>Press <kbd>Tab</kbd> or <kbd>Enter</kbd> to move down rows seamlessly.</small>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table ledger-table table-hover mb-0" id="ledger_table">
                                    <thead>
                                        <tr>
                                            <th class="text-center" style="width: 8%;">Roll No</th>
                                            <th style="width: 25%;">Student Identity Name</th>
                                            <th class="text-center" style="width: 15%;">Obtained Marks</th>
                                            <th class="text-center" style="width: 15%;">Exam Date</th>
                                            <th class="text-center" style="width: 12%;">Percentage (%)</th>
                                            <th class="text-center" style="width: 15%;">Result Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($students_list)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center py-5 text-muted">No records found.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($students_list as $student):
                                                $obtained_val = $student['obtained_marks'] !== null ? (float)$student['obtained_marks'] : '';
                                                // Assuming you fetched exam_date in your SQL query as $student['exam_date']
                                                $exam_date_val = $student['exam_date'] ?? '';
                                            ?>
                                                <tr class="student-entry-row">
                                                    <td class="text-center fw-bold font-monospace bg-light"><?= htmlspecialchars($student['roll_no'] ?? '—') ?></td>
                                                    <td><span class="fw-semibold text-dark"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></span></td>
                                                    <td class="text-center">
                                                        <input type="number" step="0.5" name="obtained_marks[<?= $student['student_id'] ?>]"
                                                            class="form-control form-control-sm mx-auto marks-input-field"
                                                            value="<?= $obtained_val ?>" min="0" autocomplete="off">
                                                    </td>
                                                    <td class="text-center">
                                                        <input type="date" name="exam_dates[<?= $student['student_id'] ?>]"
                                                            class="form-control form-control-sm mx-auto exam-date-input"
                                                            value="<?= $exam_date_val ?>">
                                                    </td>
                                                    <td class="text-center fw-bold text-secondary dynamic-percentage-cell font-monospace">—</td>
                                                    <td class="text-center">
                                                        <span class="status-badge status-pending dynamic-status-cell">Pending</span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php if (!empty($students_list)): ?>
                            <div class="card-footer bg-white border-top p-3 text-end app-no-print">
                                <a href="marks_entry.php" class="btn btn-light px-4 me-2">Clear Workspace</a>
                                <button type="submit" class="btn btn-success px-5 fw-bold">
                                    <i class="bi bi-cloud-arrow-up-fill me-2"></i>Commit Marks Ledger
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </form>
            <?php else: ?>
                <div class="card border border-dashed py-5 text-center">
                    <div class="card-body text-muted">
                        <i class="bi bi-journal-check display-3 mb-3 text-secondary d-inline-block"></i>
                        <h4 class="fw-bold text-dark">Data Verification Queue</h4>
                        <p class="mx-auto text-secondary small" style="max-width: 480px;">Please select an active evaluation term module, a class registry framework, section boundary, and a target course head above to process marks valuations.</p>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {

            // --- 1. Hierarchical Dependent Dropdowns Engine ---
            const classSelect = document.getElementById('filter_class_select');
            const sectionSelect = document.getElementById('filter_section_select');
            const subjectSelect = document.getElementById('filter_subject_select');

            // Re-inject saved filter states into the DOM
            const savedSectionId = "<?= $selected_section_id ?>";
            const savedSubjectName = "<?= htmlspecialchars($selected_subject_name) ?>";

            // A. Load Sections based on Class selection
            function loadSections(classId, preselectSectionId = '') {
                if (!classId) {
                    sectionSelect.innerHTML = '<option value="">-- Choose Class First --</option>';
                    subjectSelect.innerHTML = '<option value="">-- Choose Section First --</option>';
                    return;
                }

                sectionSelect.innerHTML = '<option value="">Loading Sections...</option>';

                fetch(`../../backend/get_sections_by_class.php?class_id=${classId}`)
                    .then(res => res.json())
                    .then(data => {
                        sectionSelect.innerHTML = '<option value="">-- Choose Section --</option>';
                        if (data.success && data.sections.length > 0) {
                            data.sections.forEach(sec => {
                                const activeOpt = (sec.id == preselectSectionId) ? 'selected' : '';
                                sectionSelect.innerHTML += `<option value="${sec.id}" ${activeOpt}>${sec.section_name}</option>`;
                            });
                            // If a section was preselected, trigger subject loading for it instantly
                            if (preselectSectionId) {
                                loadSubjects(classId, preselectSectionId, savedSubjectName);
                            }
                        } else {
                            sectionSelect.innerHTML = '<option value="">No sections found</option>';
                        }
                    })
                    .catch(() => {
                        sectionSelect.innerHTML = '<option value="">Error fetching sections</option>';
                    });
            }

            // B. Load Subjects based on Class and Section Selection
            function loadSubjects(classId, sectionId, preselectSubjectName = '') {
                if (!classId || !sectionId) {
                    subjectSelect.innerHTML = '<option value="">-- Choose Section First --</option>';
                    return;
                }

                subjectSelect.innerHTML = '<option value="">Loading Subjects...</option>';

                fetch(`../../backend/get_subjects_by_class.php?class_id=${classId}&section_id=${sectionId}`)
                    .then(res => res.json())
                    .then(data => {
                        subjectSelect.innerHTML = '<option value="">-- Choose Subject --</option>';
                        if (data.success && data.subjects.length > 0) {
                            data.subjects.forEach(sub => {
                                const activeOpt = (sub.subject_name === preselectSubjectName) ? 'selected' : '';
                                subjectSelect.innerHTML += `<option value="${sub.subject_name}" ${activeOpt}>${sub.subject_name}</option>`;
                            });
                        } else {
                            subjectSelect.innerHTML = '<option value="">No subjects registered</option>';
                        }
                    })
                    .catch(() => {
                        subjectSelect.innerHTML = '<option value="">Error fetching subjects</option>';
                    });
            }

            // Event Listeners for Dropdown Chaining
            if (classSelect) {
                classSelect.addEventListener('change', function() {
                    loadSections(this.value);
                    subjectSelect.innerHTML = '<option value="">-- Choose Section First --</option>';
                });
            }

            if (sectionSelect) {
                sectionSelect.addEventListener('change', function() {
                    loadSubjects(classSelect.value, this.value);
                });
            }

            // Add this inside your <script> tag
            function applyGlobalDate() {
                const globalDate = document.getElementById('global_date_picker').value;
                if (!globalDate) {
                    alert("Please select a date in the global date picker first.");
                    return;
                }

                // Target all date inputs in the table
                const dateInputs = document.querySelectorAll('.exam-date-input');
                dateInputs.forEach(input => {
                    input.value = globalDate;
                });
            }

            // Initial State Recovery Execution
            if (classSelect && classSelect.value) {
                loadSections(classSelect.value, savedSectionId);
            }

            // --- 2. Live Evaluation Spreadsheet Matrix Engine ---
            const globalMaxInput = document.getElementById('global_max_marks');
            const globalPassInput = document.getElementById('global_pass_marks');
            const rowEntries = document.querySelectorAll('.student-entry-row');

            function evaluateLedgerRow(row) {
                const inputField = row.querySelector('.marks-input-field');
                const percentCell = row.querySelector('.dynamic-percentage-cell');
                const statusCell = row.querySelector('.dynamic-status-cell');

                const maxMarks = parseFloat(globalMaxInput.value) || 100;
                const passMarks = parseFloat(globalPassInput.value) || 33;
                const rawVal = inputField.value;

                if (rawVal === '') {
                    percentCell.textContent = '—';
                    statusCell.textContent = 'Pending';
                    statusCell.className = 'status-badge status-pending';
                    inputField.classList.remove('is-invalid');
                    return;
                }

                const obtained = parseFloat(rawVal) || 0;

                if (obtained > maxMarks) {
                    inputField.classList.add('is-invalid');
                    percentCell.textContent = 'ERR';
                    statusCell.textContent = 'Invalid';
                    statusCell.className = 'status-badge status-fail';
                    return;
                } else {
                    inputField.classList.remove('is-invalid');
                }

                const percentage = (obtained / maxMarks) * 100;
                percentCell.textContent = percentage.toFixed(1) + '%';

                if (obtained >= passMarks) {
                    statusCell.textContent = 'Pass';
                    statusCell.className = 'status-badge status-pass';
                } else {
                    statusCell.textContent = 'Fail';
                    statusCell.className = 'status-badge status-fail';
                }
            }

            function processCompleteMatrixEvaluation() {
                rowEntries.forEach(evaluateLedgerRow);
            }

            if (rowEntries.length > 0) {
                processCompleteMatrixEvaluation();

                globalMaxInput.addEventListener('input', processCompleteMatrixEvaluation);
                globalPassInput.addEventListener('input', processCompleteMatrixEvaluation);

                rowEntries.forEach(row => {
                    const input = row.querySelector('.marks-input-field');
                    input.addEventListener('input', () => evaluateLedgerRow(row));

                    input.addEventListener('keydown', function(e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            const currentTr = this.closest('tr');
                            const nextTr = currentTr.nextElementSibling;
                            if (nextTr) {
                                const nextInput = nextTr.querySelector('.marks-input-field');
                                if (nextInput) nextInput.focus();
                            }
                        }
                    });
                });
            }

            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
            }
        });
    </script>
</body>

</html>