<?php

/**
 * Teacher Profile - Smart Choice Academy
 * View-only teacher profile information (Read Only)
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

if ($user_id) {
    $stmt = $db->prepare("
        SELECT t.*, u.full_name, u.email, u.reg_id, u.status as user_status, u.created_at as user_created_at
        FROM teachers t 
        JOIN users u ON t.user_id = u.id 
        WHERE t.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $teacher = $stmt->fetch();
    $teacher_id = $teacher['teacher_id'] ?? null;
}

// If teacher_id not found, try by reg_id
if (!$teacher_id && isset($_SESSION['reg_id'])) {
    $stmt = $db->prepare("
        SELECT t.*, u.full_name, u.email, u.reg_id, u.status as user_status, u.created_at as user_created_at
        FROM teachers t 
        JOIN users u ON t.user_id = u.id 
        WHERE t.employee_id = ?
    ");
    $stmt->execute([$_SESSION['reg_id']]);
    $teacher = $stmt->fetch();
    $teacher_id = $teacher['teacher_id'] ?? null;
}

if (!$teacher) {
    header('Location: dashboard.php?error=profile_not_found');
    exit;
}

// Get teacher statistics
$stats = [];

// Total students taught
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT s.id) as total_students
    FROM timetable_records tr
    JOIN students s ON tr.class_id = s.class_id AND tr.section_id = s.section_id
    WHERE tr.teacher_id = ? AND s.status = 'approved'
");
$stmt->execute([$teacher_id]);
$stats['total_students'] = $stmt->fetchColumn() ?: 0;

// Total classes taught
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT class_id) as total_classes
    FROM timetable_records
    WHERE teacher_id = ?
");
$stmt->execute([$teacher_id]);
$stats['total_classes'] = $stmt->fetchColumn() ?: 0;

// Total courses taught
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT course_id) as total_courses
    FROM timetable_records
    WHERE teacher_id = ?
");
$stmt->execute([$teacher_id]);
$stats['total_courses'] = $stmt->fetchColumn() ?: 0;

// Total periods per week
$stmt = $db->prepare("
    SELECT COUNT(*) as total_periods
    FROM timetable_records
    WHERE teacher_id = ?
");
$stmt->execute([$teacher_id]);
$stats['total_periods'] = $stmt->fetchColumn() ?: 0;

// Get profile completion percentage (for display only)
$profile_fields = [
    'full_name' => $teacher['full_name'],
    'email' => $teacher['email'],
    'phone' => $teacher['phone'],
    'address' => $teacher['address'],
    'qualification' => $teacher['qualification'],
    'experience_years' => $teacher['experience_years'],
    'gender' => $teacher['gender'],
    'date_of_birth' => $teacher['date_of_birth'],
    'photo' => $teacher['photo']
];
$filled_fields = 0;
foreach ($profile_fields as $value) {
    if (!empty($value)) $filled_fields++;
}
$completion_percentage = round(($filled_fields / count($profile_fields)) * 100);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Smart Choice Academy</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/teacher_sidebar.css">
    <style>
        :root {
            --primary: #4e73df;
            --primary-dark: #224abe;
            --success: #1cc88a;
            --info: #36b9cc;
            --warning: #f6c23e;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: #f5f7fb;
            font-family: 'Inter', sans-serif;
            display: flex;
        }

        .main-content {
            margin-left: 260px;
            padding: 25px 35px;
            min-height: 100vh;
            width: 100%;
            transition: all 0.3s ease;
        }

        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
        }

        /* Page Header */
        .page-header {
            margin-bottom: 30px;
        }

        .page-title {
            font-size: 24px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .page-subtitle {
            font-size: 14px;
            color: #6c757d;
        }

        /* Profile Card */
        .profile-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 25px;
        }

        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px;
            text-align: center;
            color: white;
            position: relative;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid white;
            object-fit: cover;
            margin-bottom: 15px;
            background: white;
        }

        .profile-name {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .profile-role {
            font-size: 13px;
            opacity: 0.9;
        }

        .profile-id {
            font-size: 12px;
            opacity: 0.8;
            margin-top: 8px;
        }

        /* Stats Row */
        .stat-item {
            text-align: center;
            padding: 15px;
            border-right: 1px solid #e9ecef;
        }

        .stat-item:last-child {
            border-right: none;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 800;
            color: #2c3e50;
        }

        .stat-label {
            font-size: 11px;
            color: #6c757d;
            text-transform: uppercase;
            margin-top: 5px;
        }

        /* Profile Info Sections */
        .info-section {
            padding: 25px;
            border-bottom: 1px solid #e9ecef;
        }

        .info-section:last-child {
            border-bottom: none;
        }

        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: #667eea;
            font-size: 20px;
        }

        .info-row {
            display: flex;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #f0f2f5;
        }

        .info-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .info-label {
            width: 160px;
            font-weight: 600;
            color: #6c757d;
            font-size: 13px;
        }

        .info-value {
            flex: 1;
            color: #2c3e50;
            font-size: 14px;
        }

        /* Progress Bar */
        .progress-custom {
            height: 8px;
            border-radius: 4px;
            background-color: #e9ecef;
        }

        .progress-custom-bar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 4px;
            height: 100%;
            width: 0%;
            transition: width 0.5s ease;
        }

        /* Info Badges */
        .info-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-active {
            background: #d4edda;
            color: #155724;
        }

        .badge-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .badge-male {
            background: #cfe2ff;
            color: #084298;
        }

        .badge-female {
            background: #f8d7da;
            color: #85182a;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .info-row {
                flex-direction: column;
            }

            .info-label {
                width: 100%;
                margin-bottom: 5px;
            }

            .stat-item {
                border-right: none;
                border-bottom: 1px solid #e9ecef;
            }

            .stat-item:last-child {
                border-bottom: none;
            }

            .main-content {
                padding: 15px;
            }
        }

        /* Print Styles */
        @media print {
            .no-print {
                display: none !important;
            }

            .main-content {
                margin-left: 0;
                padding: 0;
            }

            .profile-card {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }

        /* Read-only indicator */
        .readonly-badge {
            background: #e9ecef;
            color: #6c757d;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
        }
    </style>
</head>

<body>
    <?php include '../../backend/teacher_sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="page-title">
                        <i class="bi bi-person-circle me-2" style="color: #667eea;"></i>
                        My Profile
                    </h1>
                    <p class="page-subtitle">View your personal and professional information</p>
                </div>
                <div class="readonly-badge no-print">
                    <i class="bi bi-eye me-1"></i> Read Only View
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Left Column - Profile Card -->
            <div class="col-lg-4">
                <div class="profile-card">
                    <div class="profile-header">
                        <?php
                        $photo_path = !empty($teacher['photo']) && file_exists('../../' . $teacher['photo'])
                            ? '../../' . $teacher['photo']
                            : 'https://ui-avatars.com/api/?name=' . urlencode($teacher['full_name']) . '&background=667eea&color=fff&size=120';
                        ?>
                        <img src="<?= $photo_path ?>" alt="Profile Photo" class="profile-avatar">
                        <div class="profile-name"><?= htmlspecialchars($teacher['full_name']) ?></div>
                        <div class="profile-role">Teacher</div>
                        <div class="profile-id">Employee ID: <?= htmlspecialchars($teacher['employee_id'] ?? 'N/A') ?></div>
                    </div>

                    <div class="p-3">
                        <!-- Profile Completion -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="small fw-semibold">Profile Completion</span>
                                <span class="small fw-semibold"><?= $completion_percentage ?>%</span>
                            </div>
                            <div class="progress-custom">
                                <div class="progress-custom-bar" style="width: <?= $completion_percentage ?>%"></div>
                            </div>
                        </div>

                        <!-- Stats -->
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="stat-item">
                                    <div class="stat-value"><?= $stats['total_students'] ?></div>
                                    <div class="stat-label">Students</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="stat-item">
                                    <div class="stat-value"><?= $stats['total_classes'] ?></div>
                                    <div class="stat-label">Classes</div>
                                </div>
                            </div>
                            <div class="col-6 mt-2">
                                <div class="stat-item">
                                    <div class="stat-value"><?= $stats['total_courses'] ?></div>
                                    <div class="stat-label">Courses</div>
                                </div>
                            </div>
                            <div class="col-6 mt-2">
                                <div class="stat-item">
                                    <div class="stat-value"><?= $stats['total_periods'] ?></div>
                                    <div class="stat-label">Periods/Week</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column - Information Display -->
            <div class="col-lg-8">
                <!-- Personal Information -->
                <div class="profile-card">
                    <div class="info-section">
                        <div class="section-title">
                            <i class="bi bi-person-badge"></i>
                            Personal Information
                        </div>

                        <div class="info-row">
                            <div class="info-label">Full Name</div>
                            <div class="info-value"><?= htmlspecialchars($teacher['full_name']) ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Email Address</div>
                            <div class="info-value"><?= htmlspecialchars($teacher['email']) ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Phone Number</div>
                            <div class="info-value"><?= htmlspecialchars($teacher['phone'] ?? 'Not provided') ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Gender</div>
                            <div class="info-value">
                                <?php if (!empty($teacher['gender'])): ?>
                                    <span class="info-badge <?= $teacher['gender'] == 'Male' ? 'badge-male' : ($teacher['gender'] == 'Female' ? 'badge-female' : '') ?>">
                                        <?= htmlspecialchars($teacher['gender']) ?>
                                    </span>
                                <?php else: ?>
                                    Not provided
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Date of Birth</div>
                            <div class="info-value"><?= !empty($teacher['date_of_birth']) ? date('d M, Y', strtotime($teacher['date_of_birth'])) : 'Not provided' ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Address</div>
                            <div class="info-value"><?= htmlspecialchars($teacher['address'] ?? 'Not provided') ?></div>
                        </div>
                    </div>
                </div>

                <!-- Professional Information -->
                <div class="profile-card">
                    <div class="info-section">
                        <div class="section-title">
                            <i class="bi bi-briefcase"></i>
                            Professional Information
                        </div>

                        <div class="info-row">
                            <div class="info-label">Qualification</div>
                            <div class="info-value"><?= htmlspecialchars($teacher['qualification'] ?? 'Not provided') ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Experience</div>
                            <div class="info-value">
                                <?php
                                $exp = (int)($teacher['experience_years'] ?? 0);
                                echo $exp > 0 ? $exp . ' year(s)' : 'Not provided';
                                ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Employee ID</div>
                            <div class="info-value"><?= htmlspecialchars($teacher['employee_id'] ?? 'N/A') ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Registration ID</div>
                            <div class="info-value"><?= htmlspecialchars($teacher['reg_id'] ?? 'N/A') ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Joining Date</div>
                            <div class="info-value"><?= date('d M, Y', strtotime($teacher['joining_date'] ?? $teacher['user_created_at'] ?? 'now')) ?></div>
                        </div>
                    </div>
                </div>

                <!-- Account Information -->
                <div class="profile-card">
                    <div class="info-section">
                        <div class="section-title">
                            <i class="bi bi-shield-check"></i>
                            Account Information
                        </div>

                        <div class="info-row">
                            <div class="info-label">Account Status</div>
                            <div class="info-value">
                                <?php if (($teacher['user_status'] ?? 'active') == 'active'): ?>
                                    <span class="info-badge badge-active">
                                        <i class="bi bi-check-circle-fill me-1"></i> Active
                                    </span>
                                <?php else: ?>
                                    <span class="info-badge badge-inactive">
                                        <i class="bi bi-x-circle-fill me-1"></i> Inactive
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Account Created</div>
                            <div class="info-value"><?= date('d M, Y h:i A', strtotime($teacher['user_created_at'] ?? 'now')) ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Last Updated</div>
                            <div class="info-value"><?= date('d M, Y h:i A', strtotime($teacher['updated_at'] ?? 'now')) ?></div>
                        </div>
                    </div>
                </div>

                <!-- Note to Teacher -->
                <div class="profile-card no-print">
                    <div class="info-section">
                        <div class="section-title">
                            <i class="bi bi-info-circle-fill"></i>
                            Information
                        </div>
                        <div class="alert alert-info mb-0">
                            <i class="bi bi-envelope me-2"></i>
                            <strong>Need to update your profile?</strong><br>
                            Please contact the school administrator to update your personal information.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>