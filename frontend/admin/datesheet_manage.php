<?php

/**
 * Datesheet Management Module - Smart Choice Academy
 * Pivoted 2D Matrix Layout Optimized for Institutional Print Layouts
 */

// 1. Authentication, Configurations & Permission Guard
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

// 2. Generate CSRF Token for Secure Post Forms
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$messageType = '';

// 3. Handle POST Actions (Bulk Create, Update, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF Token validation failed.");
    }

    // ACTION: Bulk Add New Datesheet Schedule Entries
    if (isset($_POST['action']) && $_POST['action'] === 'bulk_create') {
        $term_id = (int)($_POST['term_id'] ?? 0);
        $class_id = (int)($_POST['class_id'] ?? 0);

        $exam_dates   = $_POST['exam_date'] ?? [];
        $subjects     = $_POST['subject_name'] ?? [];
        $start_times  = $_POST['start_time'] ?? [];
        $end_times    = $_POST['end_time'] ?? [];
        $rooms        = $_POST['room_number'] ?? [];

        if ($term_id > 0 && $class_id > 0 && !empty($exam_dates)) {
            try {
                $db->beginTransaction();
                $stmt = $db->prepare("INSERT INTO exam_datesheets (term_id, class_id, subject_name, exam_date, start_time, end_time, room_number) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $insertedRows = 0;

                for ($i = 0; $i < count($exam_dates); $i++) {
                    $subject_name = trim($subjects[$i] ?? '');
                    $exam_date    = !empty($exam_dates[$i]) ? $exam_dates[$i] : null;
                    $start_time   = !empty($start_times[$i]) ? $start_times[$i] : null;
                    $end_time     = !empty($end_times[$i]) ? $end_times[$i] : null;
                    $room_number      = trim($rooms[$i] ?? '');

                    if (!empty($subject_name) && !empty($exam_date)) {
                        $stmt->execute([$term_id, $class_id, $subject_name, $exam_date, $start_time, $end_time, $room_number]);
                        $insertedRows++;
                    }
                }

                if ($insertedRows > 0) {
                    $db->commit();
                    $message = "Successfully compiled and published {$insertedRows} exam slots!";
                    $messageType = "success";
                } else {
                    $db->rollBack();
                    $message = "Failed to process entries. Check parameter data variations.";
                    $messageType = "warning";
                }
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $message = "Critical Matrix Fault: " . $e->getMessage();
                $messageType = "danger";
            }
        } else {
            $message = "Please configure structural parameters and populate rows.";
            $messageType = "warning";
        }
    }

    // ACTION: Update Existing Datesheet Entry
    if (isset($_POST['action']) && $_POST['action'] === 'update') {
        $id = (int)($_POST['datesheet_id'] ?? 0);
        $term_id = (int)($_POST['term_id'] ?? 0);
        $class_id = (int)($_POST['class_id'] ?? 0);
        $subject_name = trim($_POST['subject_name'] ?? '');
        $exam_date = !empty($_POST['exam_date']) ? $_POST['exam_date'] : null;
        $start_time = !empty($_POST['start_time']) ? $_POST['start_time'] : null;
        $end_time = !empty($_POST['end_time']) ? $_POST['end_time'] : null;
        $room_number = trim($_POST['room_number'] ?? '');

        if ($id > 0 && $term_id > 0 && $class_id > 0 && !empty($subject_name) && !empty($exam_date)) {
            $stmt = $db->prepare("UPDATE exam_datesheets SET term_id = ?, class_id = ?, subject_name = ?, exam_date = ?, start_time = ?, end_time = ?, room_number = ? WHERE id = ?");
            if ($stmt->execute([$term_id, $class_id, $subject_name, $exam_date, $start_time, $end_time, $room_number, $id])) {
                $message = "Datesheet entry profile updated!";
                $messageType = "success";
            } else {
                $message = "Failed to update schedule configurations.";
                $messageType = "danger";
            }
        }
    }

    // ACTION: Delete Datesheet Entry
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $id = (int)($_POST['datesheet_id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->prepare("DELETE FROM exam_datesheets WHERE id = ?");
            if ($stmt->execute([$id])) {
                $message = "Datesheet slot removed successfully.";
                $messageType = "success";
            } else {
                $message = "Unable to drop target record.";
                $messageType = "danger";
            }
        }
    }
}

// 4. Fetch Core Structural Dropdown Data Arrays
$terms = $db->query("SELECT id, term_name, session_year FROM exam_terms WHERE status = 'active' ORDER BY session_year DESC, id DESC")->fetchAll();
$classes = $db->query("SELECT id, class_name FROM classes ORDER BY class_name ASC")->fetchAll();

// 5. MATRIX PROCESSING: Query database and pivot flat rows into a clean 2D layout grid array
$query = "SELECT d.*, t.term_name, t.session_year, c.class_name 
          FROM exam_datesheets d
          LEFT JOIN exam_terms t ON d.term_id = t.id
          LEFT JOIN classes c ON d.class_id = c.id
          ORDER BY d.exam_date ASC, c.class_name ASC";
$raw_datesheets = $db->query($query)->fetchAll();

// Map unique target properties dynamically to assemble grid headers and dimensions
$matrix_classes = [];
$matrix_dates = [];
$matrix_grid = [];

foreach ($raw_datesheets as $row) {
    $c_id = $row['class_id'];
    $c_name = $row['class_name'] ?? 'Unassigned';
    $e_date = $row['exam_date'];

    // Structure metadata maps
    $matrix_classes[$c_id] = $c_name;
    if (!in_array($e_date, $matrix_dates)) {
        $matrix_dates[] = $e_date;
    }

    // Place item arrays into index blocks
    $matrix_grid[$e_date][$c_id] = [
        'id' => $row['id'],
        'term_id' => $row['term_id'],
        'subject' => $row['subject_name'],
        'start_time' => $row['start_time'],
        'end_time' => $row['end_time'],
        'room' => $row['room_number']
    ];
}
// Keep classes in alpha/numeric sorted alignment
asort($matrix_classes);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datesheet Registry - Smart Choice Academy</title>
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

        .main-content {
            flex: 1;
            padding: 25px;
            transition: all 0.3s ease;
            min-width: 0;
        }

        .card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
        }

        /* 2D Dashboard Table UI styles */
        .matrix-table th {
            background-color: #212529 !important;
            color: #ffffff !important;
            font-size: 12px;
            text-transform: uppercase;
            text-align: center;
            vertical-align: middle;
            border: 1px solid #343a40;
        }

        .matrix-table td {
            text-align: center;
            vertical-align: middle;
            font-size: 13px;
            min-width: 110px;
            border: 1px solid #dee2e6;
            padding: 12px 8px;
        }

        .date-col-cell {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #212529;
            text-transform: uppercase;
            font-size: 11px !important;
        }

        .subject-cell-box {
            position: relative;
            font-weight: 700;
            color: #1e3a8a;
        }

        .cell-actions {
            display: block;
            margin-top: 4px;
        }

        .cell-actions button {
            padding: 1px 4px;
            font-size: 10px;
        }

        .bulk-header-block {
            background-color: #fdfdfd;
            border-radius: 6px;
            border: 1px solid #e9ecef;
        }

        /* Institutional Header Block Container for Paper Engine */
        .print-brand-header {
            display: none;
        }

        .print-rules-block {
            display: none;
        }

        /* RE-ENGINEERED PICTURE FORMAT LAYOUT PRINT ENGINE */
        @media print {
            @page {
                size: A4 landscape;
                /* Landscape allocation gives matrix grids room to stretch */
                margin: 1cm;
            }

            #sidebar,
            .sidebar,
            nav,
            .btn,
            .btn-close,
            .alert,
            .text-muted,
            .cell-actions,
            .card-header {
                display: none !important;
            }

            body {
                background-color: #ffffff !important;
                color: #000000 !important;
                display: block !important;
                padding: 0 !important;
                margin: 0 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .main-content {
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
            }

            .card {
                box-shadow: none !important;
                border: none !important;
                background: transparent !important;
            }

            /* Professional Institutional Centered Header Alignment */
            .print-brand-header {
                display: block !important;
                text-align: center !important;
                margin-bottom: 20px !important;
            }

            .print-school-title {
                font-size: 24px !important;
                font-weight: 800 !important;
                color: #000000 !important;
                text-transform: uppercase;
                letter-spacing: 1px;
                margin: 0 0 4px 0 !important;
                text-decoration: underline;
            }

            .print-school-sub {
                font-size: 13px !important;
                font-weight: 700 !important;
                color: #333333 !important;
                text-transform: uppercase;
                margin: 0 0 8px 0 !important;
                letter-spacing: 0.5px;
            }

            .print-doc-title {
                font-size: 14px !important;
                font-weight: 800 !important;
                color: #000000 !important;
                text-transform: uppercase;
                text-decoration: underline;
                margin: 15px 0 5px 0 !important;
            }

            /* High Contrast Institutional Document Matrix Grid */
            .matrix-table {
                width: 100% !important;
                border-collapse: collapse !important;
                margin-top: 10px !important;
            }

            .matrix-table th {
                background-color: #ffffff !important;
                color: #000000 !important;
                border: 2px solid #000000 !important;
                font-weight: 800 !important;
                text-transform: uppercase;
                font-size: 12px !important;
                padding: 10px 4px !important;
            }

            .matrix-table td {
                border: 2px solid #000000 !important;
                padding: 12px 6px !important;
                background-color: #ffffff !important;
                color: #000000 !important;
                font-size: 12px !important;
            }

            .date-col-cell {
                background-color: #ffffff !important;
                color: #000000 !important;
                font-weight: 800 !important;
                text-align: center !important;
            }

            .subject-cell-box {
                color: #000000 !important;
                text-transform: uppercase !important;
                font-weight: 700 !important;
            }

            .time-text-print {
                display: block !important;
                font-size: 9px !important;
                font-weight: 400 !important;
                color: #444444 !important;
                margin-top: 2px;
            }

            /* Clean Instruction Footnote Block Layout */
            .print-rules-block {
                display: block !important;
                margin-top: 30px !important;
                page-break-inside: avoid;
                text-align: left !important;
                padding-left: 15px;
            }

            .print-rules-title {
                font-size: 12px !important;
                font-weight: 800 !important;
                text-transform: uppercase;
                margin-bottom: 8px !important;
                text-decoration: underline;
            }

            .print-rules-list {
                font-size: 11px !important;
                list-style-type: decimal !important;
                padding-left: 20px !important;
                line-height: 1.6 !important;
                font-weight: 700 !important;
                text-transform: uppercase;
            }
        }
    </style>
</head>

<body>

    <?php include '../../backend/sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid">

            <!-- Institutional Print Header Layout Component (Matches Image Layout Style) -->
            <div class="print-brand-header">
                <h1 class="print-school-title">Smart Choice Academy</h1>
                <h3 class="print-school-sub">Main Sialkot Bypass, Gujranwala</h3>
                <div class="print-doc-title">
                    <div class="print-doc-title">
                        DATE SHEET FOR
                        <?php
                        if (!empty($raw_datesheets)) {
                            // Dynamically grab the active term name and session from the first row of data
                            echo htmlspecialchars(strtoupper($raw_datesheets[0]['term_name']));
                        } else {
                            echo "FINAL";
                        }
                        ?>
                        EXAMINATION CLASSES
                        <?php
                        if (!empty($matrix_classes)) {
                            echo htmlspecialchars(reset($matrix_classes)) . " — " . htmlspecialchars(end($matrix_classes));
                        } else {
                            echo "I — VIII";
                        }
                        ?>
                    </div>
                </div>
            </div>

            <!-- UI Top Controls -->
            <div class="d-flex justify-content-between align-items-center mb-4 app-no-print">
                <div>
                    <h2 class="mb-0 fw-bold text-dark">Examination Datesheets</h2>
                    <small class="text-muted">Dynamic Matrix Grid View — Configured for clear cross-tabulation printing</small>
                </div>
                <div>
                    <button class="btn btn-dark me-2" onclick="window.print();">
                        <i class="bi bi-printer-fill me-2"></i>Print Institutional Layout
                    </button>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bulkAddDatesheetModal">
                        <i class="bi bi-grid-3x3-gap me-2"></i>Bulk Create Datesheet
                    </button>
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                    <i class="bi <?= $messageType === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' ?> me-2"></i>
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Central Grid Card Layout -->
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table matrix-table mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 15%;">DAY AND<br>DATE</th>
                                    <?php foreach ($matrix_classes as $class_id => $class_name): ?>
                                        <th><?= htmlspecialchars($class_name) ?></th>
                                    <?php endforeach; ?>
                                    <?php if (empty($matrix_classes)): ?>
                                        <th>No Classes Configured</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($matrix_dates)): ?>
                                    <tr>
                                        <td colspan="<?= count($matrix_classes) + 1 ?>" class="text-center py-5 text-muted">
                                            <i class="bi bi-calendar-x display-6"></i>
                                            <p class="mt-2 mb-0">No active schedule entries found inside database matrix lines.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($matrix_dates as $date_string): ?>
                                        <tr>
                                            <!-- Date Column Element -->
                                            <td class="date-col-cell">
                                                <div class="fw-bold"><?= strtoupper(date('l', strtotime($date_string))) ?></div>
                                                <div class="text-secondary font-monospace"><?= date('d.m.Y', strtotime($date_string)) ?></div>
                                            </td>

                                            <!-- Dynamic Iteration Over Classes Columns -->
                                            <?php foreach ($matrix_classes as $class_id => $class_name): ?>
                                                <td>
                                                    <?php if (isset($matrix_grid[$date_string][$class_id])):
                                                        $cell = $matrix_grid[$date_string][$class_id]; ?>
                                                        <div class="subject-cell-box">
                                                            <?= htmlspecialchars($cell['subject']) ?>

                                                            <!-- Time parameters appended for print verification visibility -->
                                                            <?php if (!empty($cell['start_time'])): ?>
                                                                <span class="time-text-print d-none">
                                                                    <?= date('h:i A', strtotime($cell['start_time'])) ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>

                                                        <!-- UI Action Utilities (Hidden on print documents automatically) -->
                                                        <div class="cell-actions app-no-print">
                                                            <button class="btn btn-link text-secondary edit-datesheet-btn"
                                                                data-bs-toggle="modal" data-bs-target="#editDatesheetModal"
                                                                data-id="<?= $cell['id'] ?>" data-term="<?= $cell['term_id'] ?>"
                                                                data-class="<?= $class_id ?>" data-subject="<?= htmlspecialchars($cell['subject']) ?>"
                                                                data-date="<?= $date_string ?>" data-start="<?= $cell['start_time'] ?>"
                                                                data-end="<?= $cell['end_time'] ?>" data-room="<?= htmlspecialchars($cell['room']) ?>">
                                                                <i class="bi bi-pencil"></i>
                                                            </button>
                                                            <button class="btn btn-link text-danger delete-datesheet-btn"
                                                                data-bs-toggle="modal" data-bs-target="#deleteDatesheetModal"
                                                                data-id="<?= $cell['id'] ?>" data-label="<?= htmlspecialchars($cell['subject'] . ' (' . $class_name . ')') ?>">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted font-monospace">Holiday</span>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Rules & Guidelines Block (Matches Lower Footnote Format from Reference Image) -->
            <div class="print-rules-block">
                <div class="print-rules-title">Please Note:</div>
                <ol class="print-rules-list">
                    <li>All dues to be cleared before the examination commence.</li>
                    <li>All students must come neatly dressed in school uniform.</li>
                    <li>Use of unfair means will be marked "0".</li>
                    <li>No mobile phones or such devices to be carried to school.</li>
                    <li>No retest will be conducted for the absentees.</li>
                </ol>
            </div>

        </div>
    </main>

    <!-- 1. Bulk Add Modal Structure Component -->
    <div class="modal fade" id="bulkAddDatesheetModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen modal-dialog-scrollable">
            <form action="" method="POST" class="modal-content">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="bulk_create">

                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-grid-3x3-gap me-2"></i>Bulk Entry Timetable Matrix Generator</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body bg-light">
                    <div class="p-3 mb-4 bulk-header-block row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">1. Target Assessment Scope Term <span class="text-danger">*</span></label>
                            <select name="term_id" class="form-select" required>
                                <option value="">-- Choose Target Term Head --</option>
                                <?php foreach ($terms as $t): ?>
                                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['term_name'] . ' [' . $t['session_year'] . ']') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">2. Allocated Target Class Domain <span class="text-danger">*</span></label>
                            <select name="class_id" id="bulk_class_select" class="form-select" required>
                                <option value="">-- Choose Class Boundary Context --</option>
                                <?php foreach ($classes as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['class_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="card shadow-sm">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                            <h6 class="mb-0 fw-bold text-secondary text-uppercase tracking-wider">3. Define Dynamic Entry Lines Matrix</h6>
                            <button type="button" class="btn btn-sm btn-success" id="add_matrix_row_btn">
                                <i class="bi bi-plus-circle me-1"></i>Add Schedule Entry Row
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0" id="bulk_entry_table">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 25%;">Subject Title <span class="text-danger">*</span></th>
                                        <th style="width: 20%;">Exam Date <span class="text-danger">*</span></th>
                                        <th style="width: 18%;">Start Time</th>
                                        <th style="width: 18%;">End Time</th>
                                        <th style="width: 14%;">Room/Venue ID</th>
                                        <th style="width: 5%;" class="text-center">Drop</th>
                                    </tr>
                                </thead>
                                <tbody id="bulk_matrix_rows_container">
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-white border-top shadow-lg">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Discard Matrix</button>
                    <button type="submit" class="btn btn-primary px-5 fw-bold"><i class="bi bi-cloud-arrow-up-fill me-2"></i>Commit Entries to Database</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 2. Edit Entry Configuration Modal Component -->
    <div class="modal fade" id="editDatesheetModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form action="" method="POST" class="modal-content">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="datesheet_id" id="edit_datesheet_id">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Update Allocation Matrix Slot</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Target Evaluation Term <span class="text-danger">*</span></label>
                        <select name="term_id" id="edit_term_id" class="form-select" required>
                            <?php foreach ($terms as $t): ?>
                                <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['term_name'] . ' [' . $t['session_year'] . ']') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Allocated Class Registration <span class="text-danger">*</span></label>
                        <select name="class_id" id="edit_class_select" class="form-select" required>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['class_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Subject Title Name <span class="text-danger">*</span></label>
                        <select name="subject_name" id="edit_subject_select" class="form-select" required></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Exam Schedule Date <span class="text-danger">*</span></label>
                        <input type="date" name="exam_date" id="edit_exam_date" class="form-control" required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Window Commencement</label>
                            <input type="time" name="start_time" id="edit_start_time" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Window Termination</label>
                            <input type="time" name="end_time" id="edit_end_time" class="form-control">
                        </div>
                    </div>
                    <div>
                        <label class="form-label fw-semibold">Room Assignment / Hall ID</label>
                        <input type="text" name="room_no" id="edit_room_no" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Save Configurations</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 3. Confirm Delete Dialog Component -->
    <div class="modal fade" id="deleteDatesheetModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <form action="" method="POST" class="modal-content">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="datesheet_id" id="delete_datesheet_id">
                <div class="modal-body text-center pt-4">
                    <i class="bi bi-calendar-x text-danger display-4 mb-2 d-inline-block"></i>
                    <h5 class="fw-bold">Confirm Deletion</h5>
                    <p class="text-muted small">Are you sure you want to completely drop <strong id="delete_datesheet_label" class="text-dark"></strong> from the active matrix?</p>
                </div>
                <div class="modal-footer border-top-0 d-flex justify-content-between pt-0">
                    <button type="button" class="btn w-50 btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn w-50 btn-danger btn-sm">Delete</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {

            let loadedSubjectsCache = [];

            function loadSubjects(classId, targetSelectElement, preselectedValue = '', isBulkRow = false) {
                if (!classId) {
                    targetSelectElement.innerHTML = '<option value="">-- Select Class First --</option>';
                    targetSelectElement.disabled = true;
                    return;
                }

                targetSelectElement.disabled = true;
                targetSelectElement.innerHTML = '<option value="">Loading...</option>';

                fetch(`../../backend/get_subjects_by_class.php?class_id=${classId}`)
                    .then(response => response.json())
                    .then(data => {
                        targetSelectElement.innerHTML = '<option value="">-- Choose Subject --</option>';
                        if (data.success && data.subjects.length > 0) {
                            if (!isBulkRow) {
                                loadedSubjectsCache = data.subjects;
                            }
                            data.subjects.forEach(sub => {
                                const isSelected = (sub.subject_name === preselectedValue) ? 'selected' : '';
                                targetSelectElement.innerHTML += `<option value="${sub.subject_name}" ${isSelected}>${sub.subject_name}</option>`;
                            });
                            targetSelectElement.disabled = false;
                        } else {
                            targetSelectElement.innerHTML = '<option value="">No records allocated</option>';
                        }
                    })
                    .catch(error => {
                        console.error('Core Parsing fault:', error);
                        targetSelectElement.innerHTML = '<option value="">Fetch error structural mapping</option>';
                    });
            }

            const bulkContainer = document.getElementById('bulk_matrix_rows_container');
            const addMatrixRowBtn = document.getElementById('add_matrix_row_btn');
            const bulkClassSelect = document.getElementById('bulk_class_select');

            function injectBulkRowInstance() {
                const tr = document.createElement('tr');

                let optionsMarkup = '<option value="">-- Select Subject --</option>';
                if (loadedSubjectsCache.length > 0) {
                    loadedSubjectsCache.forEach(sub => {
                        optionsMarkup += `<option value="${sub.subject_name}">${sub.subject_name}</option>`;
                    });
                } else {
                    optionsMarkup = '<option value="">-- Choose Class Domain Above --</option>';
                }

                tr.innerHTML = `
                    <td>
                        <select name="subject_name[]" class="form-select bulk-subject-dropdown" required ${loadedSubjectsCache.length === 0 ? 'disabled' : ''}>
                            ${optionsMarkup}
                        </select>
                    </td>
                    <td><input type="date" name="exam_date[]" class="form-control" required></td>
                    <td><input type="time" name="start_time[]" class="form-control"></td>
                    <td><input type="time" name="end_time[]" class="form-control"></td>
                    <td><input type="text" name="room_no[]" class="form-control" placeholder="e.g., Room 01"></td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-link text-danger remove-bulk-row-btn"><i class="bi bi-trash3-fill fs-5"></i></button>
                    </td>
                `;
                bulkContainer.appendChild(tr);
            }

            bulkClassSelect.addEventListener('change', function() {
                const classId = this.value;
                bulkContainer.innerHTML = '';

                if (classId) {
                    fetch(`../../backend/get_subjects_by_class.php?class_id=${classId}`)
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                loadedSubjectsCache = data.subjects;
                                for (let k = 0; k < 3; k++) {
                                    injectBulkRowInstance();
                                }
                            }
                        });
                } else {
                    loadedSubjectsCache = [];
                }
            });

            addMatrixRowBtn.addEventListener('click', injectBulkRowInstance);

            bulkContainer.addEventListener('click', function(e) {
                const removeBtn = e.target.closest('.remove-bulk-row-btn');
                if (removeBtn) {
                    const row = removeBtn.closest('tr');
                    row.remove();
                }
            });

            const editClassSelect = document.getElementById('edit_class_select');
            const editSubjectSelect = document.getElementById('edit_subject_select');
            editClassSelect.addEventListener('change', function() {
                loadSubjects(this.value, editSubjectSelect);
            });

            const editButtons = document.querySelectorAll('.edit-datesheet-btn');
            editButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    document.getElementById('edit_datesheet_id').value = this.dataset.id;
                    document.getElementById('edit_term_id').value = this.dataset.term;
                    document.getElementById('edit_class_select').value = this.dataset.class;
                    document.getElementById('edit_exam_date').value = this.dataset.date;
                    document.getElementById('edit_start_time').value = this.dataset.start;
                    document.getElementById('edit_end_time').value = this.dataset.end;
                    document.getElementById('edit_room_no').value = this.dataset.room;

                    loadSubjects(this.dataset.class, editSubjectSelect, this.dataset.subject);
                });
            });

            const deleteButtons = document.querySelectorAll('.delete-datesheet-btn');
            deleteButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    document.getElementById('delete_datesheet_id').value = this.dataset.id;
                    document.getElementById('delete_datesheet_label').textContent = this.dataset.label;
                });
            });

            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
            }
        });
    </script>
</body>

</html>