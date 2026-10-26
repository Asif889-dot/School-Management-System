<?php
/**
 * Marks Entry - Teacher View
 * Smart Choice Academy
 * Teachers can enter test/exam marks for their own assigned classes, sections & courses.
 */

require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();

// Check if user is teacher
if ($_SESSION['role'] !== 'teacher') {
    header('Location: ../../index.php?error=unauthorized');
    exit;
}

$db = $pdo;

// Get teacher information
$user_id = $_SESSION['user_id'] ?? null;
$teacher_id = null;
$teacher_name = '';

if ($user_id) {
    $stmt = $db->prepare("SELECT t.teacher_id, t.employee_id, u.full_name 
                          FROM teachers t 
                          JOIN users u ON t.user_id = u.id 
                          WHERE t.user_id = ?");
    $stmt->execute([$user_id]);
    $teacher = $stmt->fetch();
    $teacher_id = $teacher['teacher_id'] ?? null;
    $teacher_name = $teacher['full_name'] ?? 'Teacher';
}

// If teacher_id not found, try by reg_id
if (!$teacher_id && isset($_SESSION['reg_id'])) {
    $stmt = $db->prepare("SELECT t.teacher_id, t.employee_id, u.full_name 
                          FROM teachers t 
                          JOIN users u ON t.user_id = u.id 
                          WHERE t.employee_id = ?");
    $stmt->execute([$_SESSION['reg_id']]);
    $teacher = $stmt->fetch();
    $teacher_id = $teacher['teacher_id'] ?? null;
    $teacher_name = $teacher['full_name'] ?? 'Teacher';
}

// Get current academic year
$stmt = $db->prepare("SELECT id, year_name FROM academic_years WHERE is_current = 'yes' AND status = 'active' LIMIT 1");
$stmt->execute();
$academic_year = $stmt->fetch();
$academic_year_id = $academic_year['id'] ?? 1;

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$messageType = '';

// Selected filters
$selected_term_id = (int)($_GET['term_id'] ?? 0);
$selected_class_id = (int)($_GET['class_id'] ?? 0);
$selected_section_id = (int)($_GET['section_id'] ?? 0);
$selected_subject_name = trim($_GET['subject_name'] ?? '');

// Handle POST save
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
    $exam_dates_array = $_POST['exam_dates'] ?? [];

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
            foreach ($obtained_marks_array as $student_id => $obtained_val) {
                if ($obtained_val === '' && empty($exam_dates_array[$student_id])) continue;
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
                    $date
                ]);
                $savedCount++;
            }

            $db->commit();
            $message = "Successfully saved marks for {$savedCount} students!";
            $messageType = "success";
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $message = "Error saving marks: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

// Fetch active exam terms
$terms = $db->query("SELECT id, term_name, session_year FROM exam_terms WHERE status = 'active' ORDER BY session_year DESC, id DESC")->fetchAll();

// Fetch classes taught by this teacher
$classes_taught = [];
$stmt = $db->prepare("
    SELECT DISTINCT c.id, c.class_name, c.numeric_grade 
    FROM timetable_records tr
    JOIN classes c ON tr.class_id = c.id
    WHERE tr.teacher_id = ? AND tr.academic_year_id = ?
    ORDER BY c.numeric_grade
");
$stmt->execute([$teacher_id, $academic_year_id]);
$classes_taught = $stmt->fetchAll();

// Fetch sections taught by teacher for selected class
$sections_taught = [];
if ($selected_class_id > 0) {
    $stmt = $db->prepare("
        SELECT DISTINCT sec.id, sec.section_name 
        FROM timetable_records tr
        JOIN sections sec ON tr.section_id = sec.id
        WHERE tr.teacher_id = ? AND tr.academic_year_id = ? AND tr.class_id = ?
        ORDER BY sec.section_name
    ");
    $stmt->execute([$teacher_id, $academic_year_id, $selected_class_id]);
    $sections_taught = $stmt->fetchAll();
}

// Fetch subjects (courses) taught by teacher for selected class & section
$subjects_taught = [];
if ($selected_class_id > 0 && $selected_section_id > 0) {
    $stmt = $db->prepare("
        SELECT DISTINCT cr.id, cr.course_name 
        FROM timetable_records tr
        JOIN courses cr ON tr.course_id = cr.id
        WHERE tr.teacher_id = ? AND tr.academic_year_id = ? AND tr.class_id = ? AND tr.section_id = ?
        ORDER BY cr.course_name
    ");
    $stmt->execute([$teacher_id, $academic_year_id, $selected_class_id, $selected_section_id]);
    $subjects_taught = $stmt->fetchAll();
}

// Fetch students for selected class & section
$students_list = [];
$configured_total_marks = 100;
$configured_passing_marks = 33;

if ($selected_term_id > 0 && $selected_class_id > 0 && $selected_section_id > 0 && !empty($selected_subject_name)) {
    // Fetch existing config
    $config_check = $db->prepare("SELECT total_marks, passing_marks FROM exam_marks WHERE term_id = ? AND class_id = ? AND section_id = ? AND subject_name = ? LIMIT 1");
    $config_check->execute([$selected_term_id, $selected_class_id, $selected_section_id, $selected_subject_name]);
    $existing_config = $config_check->fetch();
    if ($existing_config) {
        $configured_total_marks = (float)$existing_config['total_marks'];
        $configured_passing_marks = (float)$existing_config['passing_marks'];
    }

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
          AND s.status = 'approved'
        ORDER BY s.first_name ASC
    ";
    $stmt_students = $db->prepare($students_query);
    $stmt_students->execute([
        $selected_term_id,
        $selected_subject_name,
        $selected_class_id,
        $selected_section_id,
        $selected_class_id,
        $selected_section_id
    ]);
    $students_list = $stmt_students->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marks Entry - Smart Choice Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/teacher_sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background-color: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; display: flex; }
        .main-content { margin-left: 260px; padding: 20px 30px; min-height: 100vh; width: 100%; transition: all 0.3s ease; }
        @media (max-width: 992px) { .main-content { margin-left: 0; padding: 15px; } }

        .welcome-section { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 15px; padding: 20px 25px; margin-bottom: 25px; color: white; }
        .filter-section { background: white; border-radius: 12px; padding: 20px; margin-bottom: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .ledger-table th { background-color: #212529 !important; color: #ffffff !important; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; vertical-align: middle; border: 1px solid #2d3238; }
        .ledger-table td { vertical-align: middle; padding: 8px 12px; border: 1px solid #e2e8f0; }
        .marks-input-field { max-width: 90px; font-weight: 700; text-align: center; border-radius: 4px; border: 1px solid #cbd5e1; transition: all 0.2s ease; }
        .marks-input-field:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15); background-color: #fef08a; }
        .status-badge { font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 4px 8px; border-radius: 4px; }
        .status-pass { background-color: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .status-fail { background-color: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
        .status-pending { background-color: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
        .card { border: none; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        @media print { .no-print { display: none !important; } .main-content { margin-left: 0; padding: 0; } }
    </style>
</head>

<body>
    <?php include '../../backend/teacher_sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid">
            <!-- Welcome -->
            <div class="welcome-section">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="fw-bold mb-1"><i class="bi bi-pencil-square me-2"></i>Marks Entry</h4>
                        <p class="mb-0 opacity-75"><?= htmlspecialchars($teacher_name) ?> | Enter test &amp; exam marks for your classes</p>
                    </div>
                    <div>
                        <span class="badge bg-light text-dark"><i class="bi bi-mortarboard me-1"></i> <?= count($classes_taught) ?> Classes</span>
                    </div>
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                    <i class="bi <?= $messageType === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' ?> me-2"></i>
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Filter Section -->
            <div class="filter-section">
                <h6 class="fw-bold mb-3"><i class="bi bi-funnel me-2"></i>Select Class, Section &amp; Subject</h6>
                <form method="GET" action="" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">Exam Term</label>
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
                        <label class="form-label fw-bold small">Class</label>
                        <select name="class_id" id="filter_class_select" class="form-select" required>
                            <option value="">-- Select Class --</option>
                            <?php foreach ($classes_taught as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= $selected_class_id === (int)$c['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['class_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">Section</label>
                        <select name="section_id" id="filter_section_select" class="form-select" required>
                            <option value="">-- Select Section --</option>
                            <?php foreach ($sections_taught as $sec): ?>
                                <option value="<?= $sec['id'] ?>" <?= $selected_section_id === (int)$sec['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sec['section_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">Subject</label>
                        <select name="subject_name" id="filter_subject_select" class="form-select" required>
                            <option value="">-- Select Subject --</option>
                            <?php foreach ($subjects_taught as $sub): ?>
                                <option value="<?= htmlspecialchars($sub['course_name']) ?>" <?= $selected_subject_name === $sub['course_name'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sub['course_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i> Load Roster</button>
                        <a href="marks_entry.php" class="btn btn-outline-secondary ms-2"><i class="bi bi-arrow-repeat me-1"></i> Reset</a>
                    </div>
                </form>
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
                            <div class="col-auto"><span class="fw-bold text-dark text-uppercase small">Evaluation Rules:</span></div>
                            <div class="col-md-2">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-white text-muted">Max</span>
                                    <input type="number" step="0.5" name="total_marks" id="global_max_marks" class="form-control fw-bold text-center" value="<?= $configured_total_marks ?>" min="1" required>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-white text-muted">Passing</span>
                                    <input type="number" step="0.5" name="passing_marks" id="global_pass_marks" class="form-control fw-bold text-center" value="<?= $configured_passing_marks ?>" min="0" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="input-group input-group-sm">
                                    <input type="date" id="global_date" class="form-control">
                                    <button type="button" class="btn btn-sm btn-primary" onclick="document.querySelectorAll('.exam-date-input').forEach(i => i.value = document.getElementById('global_date').value)">Apply to All</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table ledger-table table-hover mb-0" id="ledger_table">
                                    <thead>
                                        <tr>
                                            <th class="text-center" style="width:8%;">Roll No</th>
                                            <th style="width:25%;">Student Name</th>
                                            <th class="text-center" style="width:15%;">Obtained</th>
                                            <th class="text-center" style="width:15%;">Exam Date</th>
                                            <th class="text-center" style="width:12%;">Percentage</th>
                                            <th class="text-center" style="width:15%;">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($students_list)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center py-5 text-muted">No approved students found in this section.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($students_list as $student):
                                                $obtained_val = $student['obtained_marks'] !== null ? (float)$student['obtained_marks'] : '';
                                                $exam_date_val = $student['exam_date'] ?? '';
                                            ?>
                                                <tr class="student-entry-row">
                                                    <td class="text-center fw-bold font-monospace bg-light"><?= htmlspecialchars($student['roll_no'] ?? '—') ?></td>
                                                    <td><span class="fw-semibold text-dark"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></span></td>
                                                    <td class="text-center">
                                                        <input type="number" step="0.5" name="obtained_marks[<?= $student['student_id'] ?>]" class="form-control form-control-sm mx-auto marks-input-field" value="<?= $obtained_val ?>" min="0" autocomplete="off">
                                                    </td>
                                                    <td class="text-center">
                                                        <input type="date" name="exam_dates[<?= $student['student_id'] ?>]" class="form-control form-control-sm mx-auto exam-date-input" value="<?= $exam_date_val ?>">
                                                    </td>
                                                    <td class="text-center fw-bold text-secondary dynamic-percentage-cell font-monospace">—</td>
                                                    <td class="text-center"><span class="status-badge status-pending dynamic-status-cell">Pending</span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php if (!empty($students_list)): ?>
                            <div class="card-footer bg-white border-top p-3 text-end no-print">
                                <button type="submit" class="btn btn-success px-5 fw-bold"><i class="bi bi-cloud-arrow-up-fill me-2"></i>Commit Marks</button>
                            </div>
                        <?php endif; ?>
                    </div>
                </form>
            <?php else: ?>
                <div class="card border py-5 text-center">
                    <div class="card-body text-muted">
                        <i class="bi bi-journal-check display-3 mb-3 text-secondary d-inline-block"></i>
                        <h4 class="fw-bold text-dark">Select Class, Section &amp; Subject</h4>
                        <p class="mx-auto small" style="max-width:480px;">Choose an exam term, your class, section, and subject to load the student roster for marks entry.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
        document.addEventListener('DOMContentLoaded', function() {
            const savedSectionId = "<?= $selected_section_id ?>";
            const savedSubjectName = "<?= htmlspecialchars($selected_subject_name) ?>";

// ============================================================
            // DYNAMIC SECTION & SUBJECT DROPDOWNS (AJAX)
            // ============================================================
            const classSelect = document.getElementById('filter_class_select');
            const sectionSelect = document.getElementById('filter_section_select');
            const subjectSelect = document.getElementById('filter_subject_select');

            function populateSelect(select, items, valueKey, labelKey) {
                select.innerHTML = '<option value="">-- Select --</option>';
                items.forEach(function(item) {
                    const opt = document.createElement('option');
                    opt.value = item[valueKey];
                    opt.textContent = item[labelKey];
                    select.appendChild(opt);
                });
            }

            // Load sections for a class, preserving the current selection if still valid
            function loadSections(classId, preserveValue) {
                sectionSelect.innerHTML = '<option value="">-- Select Section --</option>';
                if (!classId) return;

                fetch('../../backend/get_teacher_sections_subjects.php?class_id=' + classId + '&section_id=0')
                    .then(function(res) { return res.json(); })
                    .then(function(data) {
                        if (data.success && data.sections.length > 0) {
                            populateSelect(sectionSelect, data.sections, 'id', 'section_name');
                            // Restore: prefer the currently selected value, else the server-saved value
                            const desired = preserveValue || savedSectionId;
                            if (desired && data.sections.some(function(s) { return String(s.id) === String(desired); })) {
                                sectionSelect.value = String(desired);
                            }
                        } else if (data.success) {
                            sectionSelect.innerHTML = '<option value="">-- No Sections --</option>';
                        } else {
                            sectionSelect.innerHTML = '<option value="">-- Error loading --</option>';
                        }
                        // Kick off subject loading for the active section
                        loadSubjects(classId, sectionSelect.value);
                    })
                    .catch(function() {
                        sectionSelect.innerHTML = '<option value="">-- Error loading --</option>';
                    });
            }

            // Load subjects for a class+section, preserving the current selection if still valid
            function loadSubjects(classId, sectionId, preserveValue) {
                subjectSelect.innerHTML = '<option value="">-- Select Subject --</option>';
                if (!classId || !sectionId) return;

                fetch('../../backend/get_teacher_sections_subjects.php?class_id=' + classId + '&section_id=' + sectionId)
                    .then(function(res) { return res.json(); })
                    .then(function(data) {
                        if (data.success && data.subjects.length > 0) {
                            populateSelect(subjectSelect, data.subjects, 'subject_name', 'subject_name');
                            const desired = preserveValue || savedSubjectName;
                            if (desired && data.subjects.some(function(s) { return String(s.subject_name) === String(desired); })) {
                                subjectSelect.value = String(desired);
                            }
                        } else if (data.success) {
                            subjectSelect.innerHTML = '<option value="">-- No Subjects --</option>';
                        } else {
                            subjectSelect.innerHTML = '<option value="">-- Error loading --</option>';
                        }
                    })
                    .catch(function() {
                        subjectSelect.innerHTML = '<option value="">-- Error loading --</option>';
                    });
            }

            // On class change: reload sections (subject resets too)
            classSelect.addEventListener('change', function() {
                subjectSelect.innerHTML = '<option value="">-- Select Subject --</option>';
                loadSections(this.value, '');
            });

            // On section change: only reload subjects, keep the selected section intact
            sectionSelect.addEventListener('change', function() {
                loadSubjects(classSelect.value, this.value, '');
            });

            // Initial load if a class is already selected
            if (classSelect.value) {
                loadSections(classSelect.value, sectionSelect.value);
            }

            // Live evaluation matrix
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
                }
                inputField.classList.remove('is-invalid');
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

            function processCompleteMatrixEvaluation() { rowEntries.forEach(evaluateLedgerRow); }

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
                            const nextTr = this.closest('tr').nextElementSibling;
                            if (nextTr) nextTr.querySelector('.marks-input-field').focus();
                        }
                    });
                });
            }
        });
    </script>
</body>

</html>
