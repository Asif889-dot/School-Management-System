<?php
/**
 * Admission Form Submission Handler
 * Processes student admission form data
 */

require_once 'config.php';

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../frontend/admin/admission.php');
    exit;
}

$message = '';
$messageType = '';

try {
    // Handle file uploads
    $uploadDir = __DIR__ . '/../assets/uploads/admissions/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    function uploadFile($fileKey, $dir, $prefix) {
        if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION);
            $filename = $prefix . '_' . time() . '.' . $ext;
            $targetPath = $dir . $filename;
            if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $targetPath)) {
                return 'assets/uploads/admissions/' . $filename;
            }
        }
        return '';
    }
    
    $photoPath = uploadFile('std_photo', $uploadDir, 'photo');
    $fatherCnicPath = uploadFile('std_father_id_copy', $uploadDir, 'father_cnic');
    $motherCnicPath = uploadFile('std_mother_id_copy', $uploadDir, 'mother_cnic');
    $birthCertPath = uploadFile('std_birth_certificate', $uploadDir, 'birth_cert');
    $marksheetPath = uploadFile('std_marksheet', $uploadDir, 'marksheet');
    
    // Get form data
    $firstName = trim($_POST['std_first_name'] ?? '');
    $lastName = trim($_POST['std_last_name'] ?? '');
    $idProof = trim($_POST['std_id_proof'] ?? '');
    $gender = $_POST['std_gender'] ?? '';
    $religion = $_POST['std_religion'] ?? '';
    $dateOfBirth = $_POST['std_date_of_birth'] ?? '';
    $previousSchool = trim($_POST['std_previous_school'] ?? '');
    $percentage = trim($_POST['std_last_exam_percentage'] ?? '');
    $admissionType = $_POST['std_admission_type'] ?? 'New Admission';
    $fatherName = trim($_POST['std_father_name'] ?? '');
    $fatherPhone = trim($_POST['std_father_phone_no'] ?? '');
    $motherName = trim($_POST['std_mother_name'] ?? '');
    $motherPhone = trim($_POST['std_mother_phone_no'] ?? '');
    
    // Academic info - Session, Class, Section
    $academicYearId = (int)($_POST['academic_year_id'] ?? 0);
    $classId = (int)($_POST['class_id'] ?? 0);
    $sectionId = (int)($_POST['section_id'] ?? 0);
    
    // Validate required fields
    if (empty($firstName) || empty($lastName) || empty($idProof) || empty($gender) || 
        empty($religion) || empty($dateOfBirth) || empty($fatherName) || empty($fatherPhone) || 
        empty($motherName) || empty($motherPhone) || empty($academicYearId) || 
        empty($classId) || empty($sectionId)) {
        throw new Exception("All required fields must be filled.");
    }
    
    // Generate registration ID
    $regId = 'STU-' . strtoupper(uniqid());
    
    // Insert into students table with academic info
    $stmt = $pdo->prepare("INSERT INTO students (
        reg_id, first_name, last_name, id_proof, gender, religion, date_of_birth,
        previous_school, last_exam_percentage, admission_type,
        father_name, father_phone, mother_name, mother_phone,
        photo, father_cnic, mother_cnic, birth_certificate, marksheet,
        academic_year_id, class_id, section_id,
        status, admission_date
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
    
    $stmt->execute([
        $regId, $firstName, $lastName, $idProof, $gender, $religion, $dateOfBirth,
        $previousSchool, $percentage, $admissionType,
        $fatherName, $fatherPhone, $motherName, $motherPhone,
        $photoPath, $fatherCnicPath, $motherCnicPath, $birthCertPath, $marksheetPath,
        $academicYearId, $classId, $sectionId
    ]);
    
    // Store message in session and redirect
    session_start();
    $_SESSION['admission_message'] = "Admission submitted successfully! Your Registration ID: $regId";
    $_SESSION['admission_message_type'] = 'success';
    
} catch (PDOException $e) {
    session_start();
    $_SESSION['admission_message'] = "Error submitting admission: " . $e->getMessage();
    $_SESSION['admission_message_type'] = 'danger';
} catch (Exception $e) {
    session_start();
    $_SESSION['admission_message'] = $e->getMessage();
    $_SESSION['admission_message_type'] = 'danger';
}

// Redirect back to admission form
header('Location: ../frontend/admin/admission.php');
exit;
?>
