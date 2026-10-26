<?php

/**
 * Student ID Cards Generation Module - Smart Choice Academy
 * Professional ID Card Printing System - Double-Sided (Front & Back)
 * ISO/IEC 7810 ID-1 Standard (85.60mm × 53.98mm)
 */

require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();

// Fetch filter parameters
$selected_class = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$selected_section = isset($_GET['section_id']) ? (int)$_GET['section_id'] : 0;
$selected_gender = isset($_GET['gender']) ? $_GET['gender'] : '';
$print_mode = isset($_GET['print_mode']) ? $_GET['print_mode'] : 'both'; // front, back, both
$academic_year_id = isset($_SESSION['academic_year_id']) ? (int)$_SESSION['academic_year_id'] : 1;

// Fetch classes for filter dropdown
$classes = $pdo->query("SELECT id, class_name, numeric_grade FROM classes ORDER BY numeric_grade")->fetchAll();

// Fetch sections for filter dropdown
$sections = [];
if ($selected_class > 0) {
    $stmt = $pdo->prepare("SELECT id, section_name FROM sections WHERE class_id = ? ORDER BY section_name");
    $stmt->execute([$selected_class]);
    $sections = $stmt->fetchAll();
}

// Build comprehensive query with all student data
$query = "
    SELECT 
        s.*, 
        c.class_name, 
        c.numeric_grade, 
        sec.section_name
    FROM students s 
    JOIN classes c ON s.class_id = c.id 
    LEFT JOIN sections sec ON s.section_id = sec.id
    WHERE s.status = 'approved'
";

$params = [];
if ($selected_class > 0) {
    $query .= " AND s.class_id = ?";
    $params[] = $selected_class;
}
if ($selected_section > 0) {
    $query .= " AND s.section_id = ?";
    $params[] = $selected_section;
}
if ($selected_gender !== '') {
    $query .= " AND s.gender = ?";
    $params[] = $selected_gender;
}

$query .= " ORDER BY c.numeric_grade, sec.section_name, s.first_name";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Get school settings
$school_name = "Smart Choice Academy";
$school_address = "Main Sialkot Bypass, Gujranwala";
$school_phone = "+92 304 1768210";
$school_email = "info@smartchoiceacademy.edu.pk";
$school_website = "www.smartchoiceacademy.edu.pk";
$academic_year = date('Y') . " - " . (date('Y') + 1);

// Get counts for statistics
$total_students = count($students);
$with_photo = 0;
$male_count = 0;
$female_count = 0;
foreach ($students as $s) {
    if (!empty($s['photo'])) $with_photo++;
    if ($s['gender'] == 'Male') $male_count++;
    if ($s['gender'] == 'Female') $female_count++;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student ID Cards - Smart Choice Academy</title>
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
            background-color: #f0f2f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
        }

        .main-content {
            margin-left: 260px;
            padding: 20px 30px;
            min-height: 100vh;
            width: 100%;
            transition: all 0.3s ease;
        }

        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
        }

        /* ID Card Container - ISO/IEC 7810 ID-1 Standard (85.60mm × 53.98mm) */
        .id-card-container {
            display: inline-block;
            margin: 10px;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        /* Card Front & Back Wrapper for Double-Sided Printing */
        .card-front,
        .card-back {
            width: 85.60mm;
            height: 53.98mm;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            position: relative;
            font-family: 'Segoe UI', Arial, sans-serif;
            transition: transform 0.2s;
            border: 1px solid #e0e0e0;
        }

        .card-front:hover,
        .card-back:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }

        /* Card Front Styles */
        .card-header-bar {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 6px 10px;
            text-align: center;
        }

        .school-name {
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin: 0;
        }

        .school-tagline {
            font-size: 6px;
            opacity: 0.8;
            margin-top: 2px;
        }

        .card-body {
            padding: 8px 10px;
            display: flex;
            gap: 10px;
        }

        .photo-area {
            width: 70px;
            height: 80px;
            background: linear-gradient(135deg, #f5f5f5 0%, #e0e0e0 100%);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #ddd;
            overflow: hidden;
        }

        .photo-placeholder {
            font-size: 35px;
            color: #adb5bd;
        }

        .student-photo {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .info-area {
            flex: 1;
        }

        .student-name {
            font-size: 11px;
            font-weight: 800;
            color: #1e3c72;
            margin-bottom: 4px;
            text-transform: uppercase;
        }

        .info-row {
            font-size: 8px;
            margin-bottom: 2px;
            display: flex;
        }

        .info-label {
            font-weight: 700;
            width: 50px;
            color: #555;
        }

        .info-value {
            color: #333;
            flex: 1;
        }

        .card-footer {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 4px 8px;
            border-top: 1px solid #e0e0e0;
            font-size: 6px;
            text-align: center;
            color: #666;
        }

        /* Card Back Styles */
        .card-back {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        }

        .back-header {
            background: linear-gradient(135deg, #2a5298 0%, #1e3c72 100%);
            color: white;
            padding: 6px 10px;
            text-align: center;
        }

        .back-title {
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .back-body {
            padding: 10px;
        }

        .info-box {
            margin-bottom: 8px;
            padding: 5px;
            background: #f8f9fa;
            border-radius: 4px;
        }

        .info-box-title {
            font-size: 7px;
            font-weight: 800;
            color: #2a5298;
            text-transform: uppercase;
            margin-bottom: 3px;
        }

        .info-box-content {
            font-size: 7px;
            color: #333;
            line-height: 1.3;
        }

        .signature-area {
            margin-top: 8px;
            padding-top: 5px;
            border-top: 1px dashed #ccc;
            display: flex;
            justify-content: space-between;
        }

        .signature-line {
            font-size: 6px;
            text-align: center;
        }

        .signature-line .line {
            width: 60px;
            border-top: 1px solid #333;
            margin-top: 3px;
        }

        /* .qr-code {
            width: 35px;
            height: 35px;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            border-radius: 4px;
        } */

        .validity-stamp {
            position: absolute;
            bottom: 5px;
            right: 5px;
            font-size: 5px;
            color: #999;
            transform: rotate(-15deg);
        }

        /* Double-sided print support */
        @media print {
            body {
                background: white;
                margin: 0;
                padding: 0;
            }

            .no-print {
                display: none !important;
            }

            .id-card-container {
                margin: 0;
                page-break-inside: avoid;
                break-inside: avoid;
            }

            /* Front side printing */
            .card-front {
                page-break-after: avoid;
                break-inside: avoid;
            }

            /* Back side printing - appears on reverse side */
            .card-back {
                page-break-before: avoid;
                break-inside: avoid;
            }

            /* Double-sided printing support */
            @page {
                size: A4;
                margin: 0.5cm;
            }

            /* Ensure colors print correctly */
            .card-front,
            .card-back {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                box-shadow: none;
                border: 1px solid #ccc;
            }

            .card-header-bar,
            .back-header,
            .card-footer {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            /* Print both sides */
            .double-sided {
                page-break-after: always;
            }
        }

        /* Filter Section */
        .filter-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .select-all-container {
            background: white;
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .card-count {
            font-size: 14px;
            font-weight: 600;
            color: #2a5298;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .stat-number {
            font-size: 28px;
            font-weight: 800;
            color: #1e3c72;
        }

        .stat-label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
        }

        .gender-badge {
            display: inline-block;
            padding: 1px 4px;
            border-radius: 3px;
            font-size: 7px;
            font-weight: 600;
        }

        .gender-male {
            background: #cfe2ff;
            color: #084298;
        }

        .gender-female {
            background: #f8d7da;
            color: #85182a;
        }

        .gender-other {
            background: #e2e3e5;
            color: #41464b;
        }

        .print-mode-btn {
            margin: 0 5px;
        }

        .print-mode-btn.active {
            background: #1e3c72;
            color: white;
            border-color: #1e3c72;
        }
    </style>
</head>

<body>

    <?php include '../../backend/sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid">

            <!-- Header Section -->
            <div class="no-print">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="fw-bold text-dark mb-1">
                            <i class="bi bi-credit-card-2-front text-primary me-2"></i>Student ID Cards
                        </h2>
                        <p class="text-muted">Double-Sided ID Cards | ISO/IEC 7810 ID-1 Standard (85.60mm × 53.98mm)</p>
                    </div>
                    <div>
                        <div class="btn-group me-2">
                            <button class="btn btn-outline-primary btn-sm print-mode-btn <?= $print_mode == 'front' ? 'active' : '' ?>"
                                onclick="setPrintMode('front')">Front Only</button>
                            <button class="btn btn-outline-primary btn-sm print-mode-btn <?= $print_mode == 'back' ? 'active' : '' ?>"
                                onclick="setPrintMode('back')">Back Only</button>
                            <button class="btn btn-outline-primary btn-sm print-mode-btn <?= $print_mode == 'both' ? 'active' : '' ?>"
                                onclick="setPrintMode('both')">Both Sides</button>
                        </div>
                        <button class="btn btn-success me-2" onclick="selectAllCards()">
                            <i class="bi bi-check-all me-2"></i>Select All
                        </button>
                        <button class="btn btn-primary" onclick="printSelectedCards()">
                            <i class="bi bi-printer-fill me-2"></i>Print Selected
                        </button>
                    </div>
                </div>

                <!-- Printing Instructions -->
                <div class="alert alert-info mb-3">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    <strong>Double-Sided Printing Instructions:</strong>
                    <ul class="mb-0 mt-2 small">
                        <li>Select <strong>"Both Sides"</strong> mode for double-sided ID cards</li>
                        <li>In print dialog, enable <strong>"Print on both sides"</strong> option</li>
                        <li>Select <strong>"Flip on long edge"</strong> for proper alignment</li>
                        <li>Use <strong>glossy photo paper</strong> or <strong>ID card paper</strong> for best results</li>
                        <li>For laminating, use <strong>100-125 micron</strong> pouches</li>
                    </ul>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-number"><?= $total_students ?></div>
                            <div class="stat-label">Total Students</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-number"><?= $with_photo ?></div>
                            <div class="stat-label">With Photos</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-number"><?= $total_students - $with_photo ?></div>
                            <div class="stat-label">Without Photos</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-number"><?= count($classes) ?></div>
                            <div class="stat-label">Total Classes</div>
                        </div>
                    </div>
                </div>

                <!-- Filter Section -->
                <div class="filter-section">
                    <h6 class="fw-bold mb-3"><i class="bi bi-funnel me-2"></i>Filter Students</h6>
                    <form method="GET" action="" id="filterForm" class="row g-3">
                        <input type="hidden" name="print_mode" id="filter_print_mode" value="<?= $print_mode ?>">
                        <div class="col-md-3">
                            <label class="form-label">Select Class</label>
                            <select name="class_id" id="class_id" class="form-select" onchange="this.form.submit()">
                                <option value="0">All Classes</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?= $class['id'] ?>" <?= $selected_class == $class['id'] ? 'selected' : '' ?>>
                                        Grade <?= $class['numeric_grade'] ?> - <?= htmlspecialchars($class['class_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Select Section</label>
                            <select name="section_id" id="section_id" class="form-select" onchange="this.form.submit()" <?= empty($sections) ? 'disabled' : '' ?>>
                                <option value="0">All Sections</option>
                                <?php foreach ($sections as $section): ?>
                                    <option value="<?= $section['id'] ?>" <?= $selected_section == $section['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($section['section_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Select Gender</label>
                            <select name="gender" id="gender" class="form-select" onchange="this.form.submit()">
                                <option value="">All Genders</option>
                                <option value="Male" <?= $selected_gender == 'Male' ? 'selected' : '' ?>>Male</option>
                                <option value="Female" <?= $selected_gender == 'Female' ? 'selected' : '' ?>>Female</option>
                                <option value="Other" <?= $selected_gender == 'Other' ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="form-check me-3">
                                <input class="form-check-input" type="checkbox" id="showWithoutPhoto" onchange="toggleWithoutPhoto()">
                                <label class="form-check-label">Show without photo only</label>
                            </div>
                            <button type="button" class="btn btn-outline-secondary btn-sm ms-auto" onclick="resetFilters()">
                                <i class="bi bi-arrow-repeat me-1"></i>Reset
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Card Count and Selection Info -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="select-all-container">
                        <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll()">
                        <label for="selectAllCheckbox" class="mb-0">Select All Cards</label>
                        <span class="badge bg-primary ms-2" id="selectedCount">0</span>
                        <span class="text-muted ms-2">/ <span id="totalCount"><?= $total_students ?></span> students</span>
                    </div>
                    <div class="card-count">
                        <i class="bi bi-person-badge me-1"></i> Total Students: <?= $total_students ?>
                    </div>
                </div>
            </div>

            <!-- ID Cards Grid -->
            <div class="row" id="cardsContainer">
                <?php if (empty($students)): ?>
                    <div class="col-12">
                        <div class="alert alert-info text-center py-5">
                            <i class="bi bi-info-circle display-4 d-block mb-3"></i>
                            <h5>No students found</h5>
                            <p>Please adjust your filters or add approved students to generate ID cards.</p>
                            <a href="add_student.php" class="btn btn-primary mt-2">
                                <i class="bi bi-plus-circle me-2"></i>Add New Student
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($students as $student):
                        $full_name = htmlspecialchars($student['first_name'] . ' ' . $student['last_name']);
                        $reg_id = htmlspecialchars($student['reg_id']);
                        $class_name = htmlspecialchars($student['class_name']);
                        $section_name = htmlspecialchars($student['section_name'] ?? 'N/A');
                        $father_name = htmlspecialchars($student['father_name'] ?? 'N/A');
                        $mother_name = htmlspecialchars($student['mother_name'] ?? 'N/A');
                        $father_phone = htmlspecialchars($student['father_phone'] ?? 'N/A');
                        $mother_phone = htmlspecialchars($student['mother_phone'] ?? 'N/A');
                        $gender = $student['gender'] ?? 'Other';
                        $religion = htmlspecialchars($student['religion'] ?? 'N/A');
                        $dob = !empty($student['date_of_birth']) ? date('d-m-Y', strtotime($student['date_of_birth'])) : 'N/A';
                        $admission_date = !empty($student['admission_date']) ? date('d-m-Y', strtotime($student['admission_date'])) : 'N/A';
                        $photo_path = !empty($student['photo']) ? '../../' . $student['photo'] : null;
                        $gender_class = strtolower($gender) == 'male' ? 'gender-male' : (strtolower($gender) == 'female' ? 'gender-female' : 'gender-other');
                        $has_photo = $photo_path && file_exists($photo_path);
                        $reg_id_hash = substr(md5($reg_id), 0, 8);
                    ?>
                        <div class="col-md-4 col-sm-6 id-card-container" data-student-id="<?= $student['id'] ?>">

                            <!-- CARD FRONT -->
                            <div class="card-front" style="display: <?= ($print_mode == 'back') ? 'none' : 'block' ?>;">
                                <div class="card-header-bar">
                                    <div class="school-name"><?= $school_name ?></div>
                                    <div class="school-tagline">Excellence in Education</div>
                                </div>
                                <div class="card-body">
                                    <div class="photo-area">
                                        <?php if ($has_photo): ?>
                                            <img src="<?= $photo_path ?>" alt="Student Photo" class="student-photo"
                                                onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'70\' height=\'80\' viewBox=\'0 0 70 80\'%3E%3Crect width=\'70\' height=\'80\' fill=\'%23f0f0f0\'/%3E%3Ctext x=\'35\' y=\'40\' text-anchor=\'middle\' fill=\'%23999\' font-size=\'10\'%3ENo Photo%3C/text%3E%3C/svg%3E'">
                                        <?php else: ?>
                                            <div class="photo-placeholder">
                                                <i class="bi bi-person-circle"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="info-area">
                                        <div class="student-name">
                                            <?= $full_name ?>
                                            <span class="gender-badge <?= $gender_class ?> ms-1"><?= substr($gender, 0, 1) ?></span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Reg ID:</span>
                                            <span class="info-value"><?= $reg_id ?></span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Class:</span>
                                            <span class="info-value"><?= $class_name ?> - <?= $section_name ?></span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Father:</span>
                                            <span class="info-value"><?= $father_name ?></span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Contact:</span>
                                            <span class="info-value"><?= $father_phone != 'N/A' ? $father_phone : $mother_phone ?></span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">DOB:</span>
                                            <span class="info-value"><?= $dob ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <div>Valid for Academic Year: <span class="validity"><?= $academic_year ?></span></div>
                                    <div><?= $school_phone ?></div>
                                </div>
                            </div>

                            <!-- CARD BACK -->
                            <div class="card-back" style="display: <?= ($print_mode == 'front') ? 'none' : 'block' ?>; margin-top: <?= ($print_mode == 'both') ? '10px' : '0' ?>;">
                                <div class="back-header">
                                    <div class="back-title">Student Identification Card</div>
                                </div>
                                <div class="back-body">
                                    <div class="info-box">
                                        <div class="info-box-title">Important Information</div>
                                        <div class="info-box-content">
                                            • This card is the property of <?= $school_name ?><br>
                                            • Report lost cards immediately to the office<br>
                                            • Card must be worn at all times on campus<br>
                                            • Replacement fee: PKR 500/-
                                        </div>
                                    </div>

                                    <div class="info-box">
                                        <div class="info-box-title">Emergency Contact</div>
                                        <div class="info-box-content">
                                            Father: <?= $father_name ?> (<?= $father_phone != 'N/A' ? $father_phone : 'N/A' ?>)<br>
                                            <!-- Mother: <?= $mother_name ?> (<?= $mother_phone != 'N/A' ? $mother_phone : 'N/A' ?>)<br> -->
                                            School: <?= $school_phone ?>
                                        </div>
                                    </div>

                                    <div class="signature-area">
                                        <div class="signature-line">
                                            <div>Student Signature</div>
                                            <div class="line"></div>
                                        </div>
                                        <div class="signature-line">
                                            <div>Authorized Signatory</div>
                                            <div class="line"></div>
                                        </div>
                                        <!-- <div class="qr-code">
                                            <i class="bi bi-qr-code"></i>
                                        </div> -->
                                    </div>
                                </div>
                                <div class="validity-stamp">
                                    ISSUE DATE: <?= date('d-m-Y') ?>
                                </div>
                            </div>

                            <!-- Selection Checkbox -->
                            <div class="text-center mt-2 no-print">
                                <input type="checkbox" class="card-selector" data-card-id="<?= $student['id'] ?>" onchange="updateSelectedCount()">
                                <small class="text-muted">Select to print</small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let selectedCards = new Set();
        let currentPrintMode = '<?= $print_mode ?>';

        function setPrintMode(mode) {
            currentPrintMode = mode;
            document.getElementById('filter_print_mode').value = mode;

            // Update button styles
            document.querySelectorAll('.print-mode-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');

            // Show/hide front/back cards
            document.querySelectorAll('.card-front').forEach(card => {
                card.style.display = (mode === 'back') ? 'none' : 'block';
            });
            document.querySelectorAll('.card-back').forEach(card => {
                card.style.display = (mode === 'front') ? 'none' : 'block';
                if (mode === 'both') {
                    card.style.marginTop = '10px';
                } else {
                    card.style.marginTop = '0';
                }
            });
        }

        function updateSelectedCount() {
            const checkboxes = document.querySelectorAll('.card-selector:checked');
            selectedCards.clear();
            checkboxes.forEach(cb => {
                selectedCards.add(parseInt(cb.dataset.cardId));
            });
            document.getElementById('selectedCount').innerText = selectedCards.size;

            const totalCards = document.querySelectorAll('.card-selector').length;
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            if (selectAllCheckbox) {
                selectAllCheckbox.checked = selectedCards.size === totalCards && totalCards > 0;
                selectAllCheckbox.indeterminate = selectedCards.size > 0 && selectedCards.size < totalCards;
            }
        }

        function toggleSelectAll() {
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            const checkboxes = document.querySelectorAll('.card-selector');

            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
                if (selectAllCheckbox.checked) {
                    selectedCards.add(parseInt(checkbox.dataset.cardId));
                } else {
                    selectedCards.delete(parseInt(checkbox.dataset.cardId));
                }
            });
            updateSelectedCount();
        }

        function selectAllCards() {
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            if (selectAllCheckbox) {
                selectAllCheckbox.checked = true;
                toggleSelectAll();
            }
        }

        function resetFilters() {
            window.location.href = window.location.pathname + '?print_mode=' + currentPrintMode;
        }

        function printSelectedCards() {
            if (selectedCards.size === 0) {
                alert('Please select at least one student ID card to print.');
                return;
            }

            let selectedHtml = '';
            document.querySelectorAll('.id-card-container').forEach(container => {
                const studentId = parseInt(container.dataset.studentId);
                if (selectedCards.has(studentId)) {
                    const clone = container.cloneNode(true);
                    const selectorDiv = clone.querySelector('.text-center.mt-2');
                    if (selectorDiv) selectorDiv.remove();

                    if (currentPrintMode === 'both') {
                        // For double-sided, keep both front and back
                        selectedHtml += clone.outerHTML;
                    } else if (currentPrintMode === 'front') {
                        // Only front
                        const frontCard = clone.querySelector('.card-front');
                        if (frontCard) {
                            clone.innerHTML = '';
                            clone.appendChild(frontCard);
                            selectedHtml += clone.outerHTML;
                        }
                    } else if (currentPrintMode === 'back') {
                        // Only back
                        const backCard = clone.querySelector('.card-back');
                        if (backCard) {
                            clone.innerHTML = '';
                            clone.appendChild(backCard);
                            selectedHtml += clone.outerHTML;
                        }
                    }
                }
            });

            const printWindow = window.open('', '_blank', 'width=900,height=700');
            if (!printWindow) {
                alert('Please allow pop-ups to print ID cards.');
                return;
            }

            printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Student ID Cards - <?= $school_name ?></title>
                <meta charset="UTF-8">
                <style>
                    * { margin: 0; padding: 0; box-sizing: border-box; }
                    body { padding: 20px; background: white; font-family: 'Segoe UI', Arial, sans-serif; }
                    .print-container { display: flex; flex-wrap: wrap; justify-content: flex-start; }
                    .id-card-container { 
                        display: inline-block; 
                        margin: 10px; 
                        page-break-inside: avoid; 
                        break-inside: avoid;
                    }
                    .card-front, .card-back {
                        width: 85.60mm;
                        height: 53.98mm;
                        background: white;
                        border-radius: 8px;
                        border: 1px solid #ddd;
                        overflow: hidden;
                        position: relative;
                        margin: 0;
                    }
                    .card-header-bar, .back-header {
                        background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
                        color: white;
                        padding: 6px 10px;
                        text-align: center;
                    }
                    .school-name { font-size: 11px; font-weight: 800; text-transform: uppercase; }
                    .school-tagline { font-size: 6px; opacity: 0.8; }
                    .card-body { padding: 8px 10px; display: flex; gap: 10px; }
                    .photo-area { width: 70px; height: 80px; background: #f0f0f0; border-radius: 6px; display: flex; align-items: center; justify-content: center; border: 1px solid #ddd; overflow: hidden; }
                    .photo-placeholder { font-size: 35px; color: #adb5bd; }
                    .student-photo { width: 100%; height: 100%; object-fit: cover; }
                    .info-area { flex: 1; }
                    .student-name { font-size: 11px; font-weight: 800; color: #1e3c72; margin-bottom: 4px; text-transform: uppercase; }
                    .info-row { font-size: 8px; margin-bottom: 2px; display: flex; }
                    .info-label { font-weight: 700; width: 50px; color: #555; }
                    .info-value { color: #333; flex: 1; }
                    .card-footer { background: #f8f9fa; padding: 4px 8px; border-top: 1px solid #e0e0e0; font-size: 6px; text-align: center; }
                    .back-title { font-size: 9px; font-weight: 800; text-transform: uppercase; }
                    .back-body { padding: 10px; }
                    .info-box { margin-bottom: 8px; padding: 5px; background: #f8f9fa; border-radius: 4px; }
                    .info-box-title { font-size: 7px; font-weight: 800; color: #2a5298; text-transform: uppercase; }
                    .info-box-content { font-size: 7px; color: #333; }
                    .signature-area { margin-top: 8px; padding-top: 5px; border-top: 1px dashed #ccc; display: flex; justify-content: space-between; }
                    .signature-line { font-size: 6px; text-align: center; }
                    .signature-line .line { width: 60px; border-top: 1px solid #333; margin-top: 3px; }
                    .qr-code { width: 35px; height: 35px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border-radius: 4px; }
                    .validity-stamp { position: absolute; bottom: 5px; right: 5px; font-size: 5px; color: #999; transform: rotate(-15deg); }
                    .gender-badge { display: inline-block; padding: 1px 4px; border-radius: 3px; font-size: 7px; font-weight: 600; }
                    .gender-male { background: #cfe2ff; color: #084298; }
                    .gender-female { background: #f8d7da; color: #85182a; }
                    
                    @media print {
                        body { padding: 0; margin: 0; }
                        .id-card-container { page-break-inside: avoid; break-inside: avoid; }
                        .card-front, .card-back {
                            -webkit-print-color-adjust: exact !important;
                            print-color-adjust: exact !important;
                            box-shadow: none;
                        }
                        .card-header-bar, .back-header, .card-footer {
                            -webkit-print-color-adjust: exact !important;
                            print-color-adjust: exact !important;
                        }
                    }
                </style>
            </head>
            <body>
                <div class="print-container">${selectedHtml}</div>
                <script>
                    window.onload = function() {
                        setTimeout(function() {
                            window.print();
                            setTimeout(function() { window.close(); }, 1000);
                        }, 100);
                    };
                <\/script>
            </body>
            </html>
        `);
            printWindow.document.close();
        }

        function toggleWithoutPhoto() {
            const checkbox = document.getElementById('showWithoutPhoto');
            const cards = document.querySelectorAll('.id-card-container');

            cards.forEach(card => {
                const hasPhoto = card.querySelector('.student-photo') !== null &&
                    card.querySelector('.student-photo').src &&
                    !card.querySelector('.student-photo').src.includes('data:image/svg+xml');
                card.style.display = (checkbox.checked && hasPhoto) ? 'none' : '';
            });

            const visibleCards = document.querySelectorAll('.id-card-container:not([style*="display: none"])').length;
            document.getElementById('totalCount').innerText = visibleCards;
        }

        document.addEventListener('DOMContentLoaded', function() {
            updateSelectedCount();
        });
    </script>

</body>

</html>