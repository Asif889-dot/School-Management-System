<?php
// Student Admission Form - Requires login
require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();
requireRole('admin');

// Get message from session (set by backend handler)
$message = $_SESSION['admission_message'] ?? '';
$messageType = $_SESSION['admission_message_type'] ?? '';

// Clear the message from session
unset($_SESSION['admission_message'], $_SESSION['admission_message_type']);

// Fetch academic years, classes, and sections for dropdowns
$academicYears = $pdo->query("SELECT * FROM academic_years WHERE status = 'active' ORDER BY year_name DESC")->fetchAll();
$classes = $pdo->query("SELECT * FROM classes ORDER BY numeric_grade ASC")->fetchAll();
$sections = $pdo->query("SELECT s.*, c.class_name FROM sections s LEFT JOIN classes c ON s.class_id = c.id ORDER BY c.numeric_grade, s.section_name")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Admission | SMS</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">

    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --accent-purple: #764ba2;
        }

        .admission-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            background: #fff;
            overflow: hidden;
        }

        .form-header {
            background: var(--primary-gradient);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .section-title {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--accent-purple);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            border-bottom: 2px solid #f1f1f1;
            padding-bottom: 8px;
            margin-bottom: 25px;
            margin-top: 20px;
        }

        .required-star {
            color: #e63946;
            margin-left: 2px;
        }

        .optional-badge {
            font-size: 0.65rem;
            padding: 2px 8px;
            border-radius: 50px;
            background: #f8f9fa;
            color: #6c757d;
            border: 1px solid #dee2e6;
        }

        .form-control, .form-select {
            border: 2px solid #edf2f7;
            border-radius: 12px;
            padding: 12px 15px;
            transition: 0.3s;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--accent-purple);
            box-shadow: none;
        }

        .preview-wrapper {
            position: relative;
            width: 100%;
            margin-bottom: 10px;
        }

        .preview-box {
            width: 100%;
            height: 160px;
            border: 2px dashed #cbd5e0;
            border-radius: 15px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: #fbfcfe;
            cursor: pointer;
            overflow: hidden;
            position: relative;
            transition: 0.3s;
        }

        .preview-box:hover {
            border-color: var(--accent-purple);
            background: #f5f7ff;
        }

        .preview-box img {
            display: none;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .preview-box i {
            font-size: 2.5rem;
            color: #cbd5e0;
        }

        .preview-box span {
            font-size: 0.7rem;
            color: #a0aec0;
            margin-top: 5px;
            font-weight: 500;
        }

        .preview-box.active {
            border: 2px solid var(--accent-purple);
        }

        .preview-box.active img {
            display: block;
        }

        .preview-box.active i, .preview-box.active span {
            display: none;
        }

        .btn-remove {
            position: absolute;
            top: -10px;
            right: -10px;
            display: none;
            background: #ff4757;
            color: white;
            border: none;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            font-size: 14px;
            z-index: 10;
        }

        .preview-box.active + .btn-remove {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-submit {
            background: var(--primary-gradient);
            border: none;
            padding: 15px 60px;
            border-radius: 12px;
            font-weight: 600;
            color: white;
            font-size: 1rem;
            transition: 0.4s;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(118, 75, 162, 0.3);
        }

        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            background: #f3f4f6;
            padding: 20px;
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <?php include '../../backend/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Header -->
        <header class="top-header">
            <div class="group-dropdown">
                <h5 class="mb-0"><i class="bi bi-person-plus-fill me-2"></i>Student Admission</h5>
            </div>
            <div class="user-menu">
                <div class="text-end d-none d-md-block">
                    <div class="fw-semibold"><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
                    <small class="text-muted">Administrator</small>
                </div>
                <div class="user-avatar">
                    <?php echo strtoupper(substr($_SESSION['full_name'], 0, 2)); ?>
                </div>
            </div>
        </header>

        <!-- Content -->
        <div class="dashboard-content">
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <div class="row justify-content-center">
                <div class="col-xl-11">
                    <div class="admission-card">
                        <div class="form-header">
                            <h4 class="fw-bold mb-1">Student Admission Form</h4>
                            <p class="mb-0 opacity-75">Academic Session 2026-2027</p>
                        </div>

                        <form action="../../backend/admission_save.php" method="POST" enctype="multipart/form-data" class="p-4 p-md-5">

                            <div class="row g-4 mb-4">
                                <div class="col-12">
                                    <h5 class="section-title"><i class="bi bi-person-circle me-2"></i> Student Basic Info</h5>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Student Photo<span class="required-star">*</span></label>
                                    <div class="preview-wrapper">
                                        <div class="preview-box" id="box-photo" onclick="document.getElementById('input-photo').click()">
                                            <i class="bi bi-camera"></i>
                                            <span>Click to Upload</span>
                                            <img id="img-photo" src="">
                                        </div>
                                        <button type="button" class="btn-remove" onclick="removePreview('input-photo', 'img-photo', 'box-photo')"><i class="bi bi-x"></i></button>
                                    </div>
                                    <input type="file" id="input-photo" name="std_photo" hidden accept="image/*" required onchange="handlePreview(this, 'img-photo', 'box-photo')">
                                </div>

                                <div class="col-md-9">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold">First Name<span class="required-star">*</span></label>
                                            <input type="text" class="form-control" name="std_first_name" placeholder="Enter first name" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold">Last Name<span class="required-star">*</span></label>
                                            <input type="text" class="form-control" name="std_last_name" placeholder="Enter last name" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold">B-Form / ID Number<span class="required-star">*</span></label>
                                            <input type="text" class="form-control" name="std_id_proof" placeholder="xxxxx-xxxxxxx-x" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small fw-bold">Gender<span class="required-star">*</span></label>
                                            <select class="form-select" name="std_gender" required>
                                                <option value="">Select</option>
                                                <option value="Male">Male</option>
                                                <option value="Female">Female</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small fw-bold">Religion<span class="required-star">*</span></label>
                                            <select class="form-select" name="std_religion" required>
                                                <option value="Muslim">Muslim</option>
                                                <option value="Christian">Christian</option>
                                                <option value="Other">Other</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold">Date of Birth<span class="required-star">*</span></label>
                                            <input type="date" class="form-control" name="std_date_of_birth" required>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Academic Info Section - Session, Class, Section -->
                            <div class="row g-4 mb-4">
                                <div class="col-12">
                                    <h5 class="section-title"><i class="bi bi-calendar-range me-2"></i> Academic Information</h5>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Academic Session<span class="required-star">*</span></label>
                                    <select class="form-select" name="academic_year_id" required>
                                        <option value="">Select Session</option>
                                        <?php foreach ($academicYears as $year): ?>
                                            <option value="<?php echo $year['id']; ?>"><?php echo htmlspecialchars($year['year_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Class<span class="required-star">*</span></label>
                                    <select class="form-select" name="class_id" id="classSelect" required onchange="loadSections()">
                                        <option value="">Select Class</option>
                                        <?php foreach ($classes as $class): ?>
                                            <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['class_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Section<span class="required-star">*</span></label>
                                    <select class="form-select" name="section_id" id="sectionSelect" required>
                                        <option value="">Select Section</option>
                                        <?php foreach ($sections as $section): ?>
                                            <option value="<?php echo $section['id']; ?>" data-class-id="<?php echo $section['class_id']; ?>" class="section-option" style="display:none;">
                                                <?php echo htmlspecialchars($section['section_name'] . ' - ' . $section['class_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="row g-4 mb-4">
                                <div class="col-12">
                                    <h5 class="section-title"><i class="bi bi-mortarboard me-2"></i> Previous School History</h5>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Last School Attended</label>
                                    <input type="text" class="form-control" name="std_previous_school" placeholder="School name and city">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Percentage / Grade</label>
                                    <input type="text" class="form-control" name="std_last_exam_percentage" placeholder="e.g. 85%">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Admission Type<span class="required-star">*</span></label>
                                    <select class="form-select" name="std_admission_type" required>
                                        <option value="New Admission">New Admission</option>
                                        <option value="Transfer">Transfer Case</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row g-4 mb-4">
                                <div class="col-12">
                                    <h5 class="section-title"><i class="bi bi-shield-check me-2"></i> Parental Info & Verification</h5>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Father's CNIC Copy<span class="required-star">*</span></label>
                                    <div class="preview-wrapper">
                                        <div class="preview-box" id="box-father-cnic" onclick="document.getElementById('input-father-cnic').click()">
                                            <i class="bi bi-person-vcard"></i>
                                            <span>Father's ID</span>
                                            <img id="img-father-cnic" src="">
                                        </div>
                                        <button type="button" class="btn-remove" onclick="removePreview('input-father-cnic', 'img-father-cnic', 'box-father-cnic')"><i class="bi bi-x"></i></button>
                                    </div>
                                    <input type="file" id="input-father-cnic" name="std_father_id_copy" hidden accept="image/*" required onchange="handlePreview(this, 'img-father-cnic', 'box-father-cnic')">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Mother's CNIC Copy<span class="required-star">*</span></label>
                                    <div class="preview-wrapper">
                                        <div class="preview-box" id="box-mother-cnic" onclick="document.getElementById('input-mother-cnic').click()">
                                            <i class="bi bi-person-vcard-fill"></i>
                                            <span>Mother's ID</span>
                                            <img id="img-mother-cnic" src="">
                                        </div>
                                        <button type="button" class="btn-remove" onclick="removePreview('input-mother-cnic', 'img-mother-cnic', 'box-mother-cnic')"><i class="bi bi-x"></i></button>
                                    </div>
                                    <input type="file" id="input-mother-cnic" name="std_mother_id_copy" hidden accept="image/*" required onchange="handlePreview(this, 'img-mother-cnic', 'box-mother-cnic')">
                                </div>

                                <div class="col-md-6">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold">Father's Full Name<span class="required-star">*</span></label>
                                            <input type="text" class="form-control" name="std_father_name" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold">Father's Contact<span class="required-star">*</span></label>
                                            <input type="tel" class="form-control" name="std_father_phone_no" placeholder="03xx-xxxxxxx" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold">Mother's Full Name<span class="required-star">*</span></label>
                                            <input type="text" class="form-control" name="std_mother_name" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold">Mother's Contact<span class="required-star">*</span></label>
                                            <input type="tel" class="form-control" name="std_mother_phone_no" placeholder="03xx-xxxxxxx" required>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-4 mb-4">
                                <div class="col-12">
                                    <h5 class="section-title"><i class="bi bi-folder-plus me-2"></i> Additional Documents</h5>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Birth Certificate / B-Form <span class="optional-badge">Optional</span></label>
                                    <div class="preview-wrapper">
                                        <div class="preview-box" id="box-bform" onclick="document.getElementById('input-bform').click()">
                                            <i class="bi bi-file-earmark-person"></i>
                                            <span>Click to Upload</span>
                                            <img id="img-bform" src="">
                                        </div>
                                        <button type="button" class="btn-remove" onclick="removePreview('input-bform', 'img-bform', 'box-bform')"><i class="bi bi-x"></i></button>
                                    </div>
                                    <input type="file" id="input-bform" name="std_birth_certificate" hidden accept="image/*" onchange="handlePreview(this, 'img-bform', 'box-bform')">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Last Academic Marksheet <span class="optional-badge">Optional</span></label>
                                    <div class="preview-wrapper">
                                        <div class="preview-box" id="box-marksheet" onclick="document.getElementById('input-marksheet').click()">
                                            <i class="bi bi-journal-text"></i>
                                            <span>Click to Upload</span>
                                            <img id="img-marksheet" src="">
                                        </div>
                                        <button type="button" class="btn-remove" onclick="removePreview('input-marksheet', 'img-marksheet', 'box-marksheet')"><i class="bi bi-x"></i></button>
                                    </div>
                                    <input type="file" id="input-marksheet" name="std_marksheet" hidden accept="image/*" onchange="handlePreview(this, 'img-marksheet', 'box-marksheet')">
                                </div>
                            </div>

                            <div class="text-center pt-4">
                                <button type="submit" class="btn btn-submit shadow">Complete Registration</button>
                                <p class="mt-3 text-muted small">By submitting, you agree that all provided information is accurate.</p>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/admin.js"></script>
    
    <script>
        function handlePreview(input, imageId, boxId) {
            const file = input.files[0];
            if (file) {
                if (!file.type.startsWith('image/')) {
                    alert("Only image files (JPG, PNG) are allowed.");
                    input.value = "";
                    return;
                }
                const reader = new FileReader();
                reader.onload = function (e) {
                    document.getElementById(imageId).src = e.target.result;
                    document.getElementById(boxId).classList.add('active');
                }
                reader.readAsDataURL(file);
            }
        }

        function removePreview(inputId, imageId, boxId) {
            document.getElementById(inputId).value = "";
            document.getElementById(imageId).src = "";
            document.getElementById(boxId).classList.remove('active');
        }

        function loadSections() {
            const classId = document.getElementById('classSelect').value;
            const sectionOptions = document.querySelectorAll('.section-option');
            const sectionSelect = document.getElementById('sectionSelect');
            
            // Reset section dropdown
            sectionSelect.value = "";
            
            if (classId) {
                sectionOptions.forEach(option => {
                    if (option.getAttribute('data-class-id') === classId) {
                        option.style.display = '';
                    } else {
                        option.style.display = 'none';
                    }
                });
            } else {
                sectionOptions.forEach(option => {
                    option.style.display = 'none';
                });
            }
        }
    </script>
</body>

</html>
