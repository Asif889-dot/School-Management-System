<?php
// Start output buffering at the VERY beginning
ob_start();

// Initialize session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

require_once '../../backend/config.php';

$db = $pdo;
$mode = $_GET['mode'] ?? 'student';
$term_id = (int)($_GET['term_id'] ?? 0);
$filter_id = (int)($_GET['filter_id'] ?? 0);
$report_students = [];

// Fetch students based on filters
if ($term_id > 0 && $filter_id > 0) {
    try {
        if ($mode == 'student') {
            $stmt = $db->prepare("SELECT s.*, c.class_name, sec.section_name 
                FROM students s 
                JOIN classes c ON s.class_id = c.id 
                JOIN sections sec ON s.section_id = sec.id 
                WHERE s.id = ?");
            $stmt->execute([$filter_id]);
            $report_students = $stmt->fetchAll();
        } elseif ($mode == 'section') {
            $stmt = $db->prepare("SELECT s.*, c.class_name, sec.section_name 
                FROM students s 
                JOIN classes c ON s.class_id = c.id 
                JOIN sections sec ON s.section_id = sec.id 
                WHERE s.section_id = ?");
            $stmt->execute([$filter_id]);
            $report_students = $stmt->fetchAll();
        } elseif ($mode == 'class') {
            $stmt = $db->prepare("SELECT s.*, c.class_name, sec.section_name 
                FROM students s 
                JOIN classes c ON s.class_id = c.id 
                JOIN sections sec ON s.section_id = sec.id 
                WHERE s.class_id = ?");
            $stmt->execute([$filter_id]);
            $report_students = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $report_students = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Official Academic Transcript | Smart Choice Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #eef2f7;
            font-family: 'Segoe UI', Arial, sans-serif;
        }

        @media print {
            body {
                background: white;
                padding: 0;
                margin: 0;
            }

            .no-print {
                display: none;
            }

            .page-break {
                page-break-after: always;
            }

            .report-card {
                box-shadow: none;
                margin: 0;
                border: 1px solid #ddd;
                page-break-inside: avoid;
            }

            .container {
                padding: 0 !important;
                max-width: 100% !important;
            }
        }

        .report-card {
            background: white;
            max-width: 1000px;
            margin: 2rem auto;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .school-header {
            background: linear-gradient(135deg, #1a3a5c 0%, #0d2b46 100%);
            color: white;
            padding: 2rem;
            text-align: center;
            border-bottom: 4px solid #f4a261;
        }

        .school-header h2 {
            font-weight: 700;
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }

        .school-header p {
            opacity: 0.9;
            margin-bottom: 0;
            font-size: 0.85rem;
        }

        .academic-badge {
            background: rgba(255, 255, 255, 0.2);
            display: inline-block;
            padding: 0.3rem 1.5rem;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: 12px;
            letter-spacing: 1px;
        }

        .student-info-card {
            background: #f8f9fa;
            margin: 1.5rem;
            padding: 1.2rem 1.5rem;
            border-radius: 12px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            border: 1px solid #e0e0e0;
        }

        .info-block {
            flex: 1;
            min-width: 180px;
            margin-bottom: 10px;
        }

        .info-label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
            color: #6c757d;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 1rem;
            font-weight: 700;
            color: #2c3e50;
        }

        .marks-table-wrapper {
            margin: 0 1.5rem 1.5rem 1.5rem;
            overflow-x: auto;
        }

        .subject-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        .subject-table th {
            background: #e9ecef;
            color: #495057;
            font-weight: 700;
            padding: 12px;
            border: 1px solid #dee2e6;
            text-align: center;
        }

        .subject-table td {
            padding: 10px 12px;
            border: 1px solid #dee2e6;
            vertical-align: middle;
        }

        .subject-table td:first-child {
            font-weight: 600;
        }

        .grade-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.8rem;
        }

        .grade-A1 {
            background: #d4edda;
            color: #155724;
        }

        .grade-A2 {
            background: #d4edda;
            color: #155724;
        }

        .grade-B1 {
            background: #fff3cd;
            color: #856404;
        }

        .grade-C {
            background: #f8d7da;
            color: #721c24;
        }

        .summary-card {
            background: #f8f9fa;
            margin: 0 1.5rem 1.5rem 1.5rem;
            border-radius: 12px;
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
            border: 1px solid #e0e0e0;
        }

        .summary-item {
            text-align: center;
            flex: 1;
            min-width: 100px;
        }

        .summary-label {
            font-size: 0.7rem;
            font-weight: 600;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .summary-number {
            font-size: 1.4rem;
            font-weight: 800;
            color: #2c3e50;
        }

        .signature-area {
            display: flex;
            justify-content: space-between;
            margin: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid #dee2e6;
        }

        .signature-box {
            width: 45%;
            text-align: center;
        }

        .signature-line {
            border-top: 1px solid #333;
            width: 80%;
            margin: 0.5rem auto 0.3rem auto;
        }

        .signature-text {
            font-size: 0.7rem;
            color: #6c757d;
        }

        .footer-note {
            background: #f8f9fa;
            text-align: center;
            font-size: 0.7rem;
            color: #6c757d;
            padding: 10px;
            border-top: 1px solid #dee2e6;
        }

        .alert-placeholder {
            max-width: 600px;
            margin: 2rem auto;
        }
    </style>
</head>

<body>
    <?php if (file_exists('../../backend/sidebar.php')): ?>
        <div class="no-print">
            <?php include_once '../../backend/sidebar.php'; ?>
        </div>
    <?php endif; ?>

    <div class="container py-4">
        <!-- Filter Panel -->
        <div class="card no-print shadow-sm mb-4">
            <div class="card-body">
                <h5 class="card-title text-primary mb-3">Generate Academic Reports</h5>
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">Selection Mode</label>
                        <select name="mode" id="mode_select" class="form-select" onchange="loadFilters()">
                            <option value="student" <?= $mode == 'student' ? 'selected' : '' ?>>Individual Student</option>
                            <option value="section" <?= $mode == 'section' ? 'selected' : '' ?>>Section Wise</option>
                            <option value="class" <?= $mode == 'class' ? 'selected' : '' ?>>Class Wise</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Select Entity</label>
                        <select name="filter_id" id="filter_select" class="form-select" required>
                            <option value="">-- Select --</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">Academic Term</label>
                        <select name="term_id" class="form-select" required>
                            <option value="">-- Select Term --</option>
                            <?php
                            try {
                                $terms = $db->query("SELECT id, term_name FROM exam_terms ORDER BY id");
                                foreach ($terms as $t):
                            ?>
                                    <option value="<?= $t['id'] ?>" <?= ($term_id == $t['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($t['term_name']) ?>
                                    </option>
                            <?php
                                endforeach;
                            } catch (PDOException $e) {
                                echo '<option disabled>No terms available</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Generate Records</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Report Cards -->
        <?php if (!empty($report_students)): ?>
            <?php foreach ($report_students as $student):
                // Fetch marks for this student
                $marks = [];
                try {
                    $marksStmt = $db->prepare("SELECT * FROM exam_marks WHERE student_id = ? AND term_id = ?");
                    $marksStmt->execute([$student['id'], $term_id]);
                    $marks = $marksStmt->fetchAll();
                } catch (PDOException $e) {
                    $marks = [];
                }

                if (empty($marks)) continue;

                // Calculate totals
                $totalObtained = 0;
                $totalMax = 0;
                foreach ($marks as $m) {
                    $totalObtained += floatval($m['obtained_marks']);
                    $totalMax += floatval($m['total_marks']);
                }
                $overallPercent = ($totalMax > 0) ? ($totalObtained / $totalMax) * 100 : 0;

                // Calculate GPA
                $gpa = 0;
                if ($overallPercent >= 90) $gpa = 4.0;
                elseif ($overallPercent >= 80) $gpa = 3.6;
                elseif ($overallPercent >= 70) $gpa = 3.0;
                elseif ($overallPercent >= 60) $gpa = 2.5;
                elseif ($overallPercent >= 50) $gpa = 2.0;
                else $gpa = 1.0;

                // Final grade
                if ($overallPercent >= 90) $finalGrade = 'A1 (Outstanding)';
                elseif ($overallPercent >= 80) $finalGrade = 'A2 (Excellent)';
                elseif ($overallPercent >= 70) $finalGrade = 'B1 (Very Good)';
                elseif ($overallPercent >= 60) $finalGrade = 'B2 (Good)';
                elseif ($overallPercent >= 50) $finalGrade = 'C (Satisfactory)';
                else $finalGrade = 'D (Needs Improvement)';

                // Get term name
                $termName = '';
                try {
                    $termStmt = $db->prepare("SELECT term_name FROM exam_terms WHERE id = ?");
                    $termStmt->execute([$term_id]);
                    $termRow = $termStmt->fetch();
                    $termName = $termRow['term_name'] ?? 'Current Term';
                } catch (PDOException $e) {
                    $termName = 'Current Term';
                }
            ?>
                <div class="report-card page-break">
                    <div class="school-header">
                        <h2>SMART CHOICE ACADEMY</h2>
                        <p>Affiliated to Central Board of Excellence | Est. 2005</p>
                        <div class="academic-badge">OFFICIAL ACADEMIC TRANSCRIPT</div>
                    </div>

                    <div class="student-info-card">
                        <div class="info-block">
                            <div class="info-label">STUDENT NAME</div>
                            <div class="info-value"><?= htmlspecialchars($student['first_name'] . ' ' . ($student['last_name'] ?? '')) ?></div>
                        </div>
                        <div class="info-block">
                            <div class="info-label">CLASS & SECTION</div>
                            <div class="info-value"><?= htmlspecialchars($student['class_name'] ?? 'N/A') ?> - <?= htmlspecialchars($student['section_name'] ?? 'N/A') ?></div>
                        </div>
                        <div class="info-block">
                            <div class="info-label">TERM</div>
                            <div class="info-value"><?= htmlspecialchars($termName) ?></div>
                        </div>
                        <div class="info-block">
                            <div class="info-label">ISSUE DATE</div>
                            <div class="info-value"><?= date('d M, Y') ?></div>
                        </div>
                    </div>

                    <div class="marks-table-wrapper">
                        <table class="subject-table">
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Max Marks</th>
                                    <th>Obtained</th>
                                    <th>Percentage</th>
                                    <th>Grade</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($marks as $mark):
                                    $percentVal = ($mark['total_marks'] > 0) ? ($mark['obtained_marks'] / $mark['total_marks']) * 100 : 0;
                                    $gradeLetter = '';
                                    $gradeClass = '';
                                    if ($percentVal >= 90) {
                                        $gradeLetter = 'A1';
                                        $gradeClass = 'grade-A1';
                                    } elseif ($percentVal >= 80) {
                                        $gradeLetter = 'A2';
                                        $gradeClass = 'grade-A2';
                                    } elseif ($percentVal >= 70) {
                                        $gradeLetter = 'B1';
                                        $gradeClass = 'grade-B1';
                                    } else {
                                        $gradeLetter = 'C';
                                        $gradeClass = 'grade-C';
                                    }
                                ?>
                                    <tr>
                                        <td><?= htmlspecialchars($mark['subject_name']) ?></td>
                                        <td class="text-center"><?= number_format($mark['total_marks'], 0) ?></td>
                                        <td class="text-center fw-bold"><?= number_format($mark['obtained_marks'], 1) ?></td>
                                        <td class="text-center"><?= number_format($percentVal, 1) ?>%</td>
                                        <td class="text-center">
                                            <span class="grade-badge <?= $gradeClass ?>"><?= $gradeLetter ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="summary-card">
                        <div class="summary-item">
                            <div class="summary-label">TOTAL</div>
                            <div class="summary-number"><?= number_format($totalObtained, 1) ?> / <?= number_format($totalMax, 0) ?></div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-label">PERCENTAGE</div>
                            <div class="summary-number"><?= number_format($overallPercent, 1) ?>%</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-label">GPA</div>
                            <div class="summary-number"><?= number_format($gpa, 2) ?></div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-label">GRADE</div>
                            <div class="summary-number" style="font-size: 1rem;"><?= $finalGrade ?></div>
                        </div>
                    </div>

                    <div class="signature-area">
                        <div class="signature-box">
                            <div class="signature-line"></div>
                            <div class="signature-text">Parent/Guardian Signature</div>
                        </div>
                        <div class="signature-box">
                            <div class="signature-line"></div>
                            <div class="signature-text">Principal/Authorized Signatory</div>
                        </div>
                    </div>

                    <div class="footer-note">
                        This is a computer-generated document. No signature is required for verification.
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <?php if ($_SERVER['REQUEST_METHOD'] == 'GET' && (isset($_GET['term_id']) && $_GET['term_id'] > 0)): ?>
                <div class="alert alert-warning alert-placeholder text-center">
                    <i class="bi bi-exclamation-triangle fs-3"></i>
                    <h5 class="mt-2">No Records Found</h5>
                    <p>No student or marks data available for the selected filters.</p>
                </div>
            <?php else: ?>
                <div class="alert alert-secondary alert-placeholder text-center">
                    <i class="bi bi-info-circle fs-3"></i>
                    <h5 class="mt-2">Select Filters</h5>
                    <p>Please select a term and entity to generate the report card.</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="no-print text-center mb-4">
        <button onclick="window.print();" class="btn btn-outline-secondary px-4">
            Print / Save as PDF
        </button>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function loadFilters() {
            const mode = document.getElementById('mode_select').value;
            const filterSelect = document.getElementById('filter_select');

            filterSelect.innerHTML = '<option value="">Loading...</option>';

            fetch(`../../backend/get_filter_data.php?mode=${mode}`)
                .then(response => response.json())
                .then(data => {
                    if (data && data.length > 0) {
                        filterSelect.innerHTML = '<option value="">-- Select --</option>' +
                            data.map(item => `<option value="${item.id}">${escapeHtml(item.name)}</option>`).join('');
                    } else {
                        filterSelect.innerHTML = '<option value="">No options available</option>';
                    }

                    // Preselect if filter_id exists in URL
                    const urlParams = new URLSearchParams(window.location.search);
                    const filterId = urlParams.get('filter_id');
                    if (filterId) {
                        filterSelect.value = filterId;
                    }
                })
                .catch(error => {
                    console.error('Error loading filters:', error);
                    filterSelect.innerHTML = '<option value="">Error loading data</option>';
                });
        }

        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/[&<>]/g, function(m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            loadFilters();
        });
    </script>
</body>

</html>
<?php
ob_end_flush();
?>