<?php
session_start();
// Include your PDO connection ($pdo)
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Start Transaction
        $pdo->beginTransaction();

        // 1. AUTO-GENERATE TEACHER ID (Server-side to prevent duplicates)
        $year = date("Y");
        $prefix = "TCH-" . $year . "-";

        $idStmt = $pdo->prepare("SELECT employee_id FROM teachers WHERE employee_id LIKE ? ORDER BY employee_id DESC LIMIT 1");
        $idStmt->execute([$prefix . '%']);
        $lastId = $idStmt->fetchColumn();

        if ($lastId) {
            $number = intval(substr($lastId, -3));
            $employee_id = $prefix . str_pad($number + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $employee_id = $prefix . '001';
        }

        // 2. COLLECT & SANITIZE INPUTS
        $user_id          = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);
        $qualification    = htmlspecialchars($_POST['qualification'] ?? '');
        $experience_years = filter_input(INPUT_POST, 'experience_years', FILTER_SANITIZE_NUMBER_INT);
        $gender           = $_POST['gender'] ?? 'Other';
        $date_of_birth    = $_POST['date_of_birth'] ?: null;
        $phone            = htmlspecialchars($_POST['phone'] ?? '');
        $address          = htmlspecialchars($_POST['address'] ?? '');
        $joining_date     = $_POST['joining_date'] ?: date('Y-m-d');
        $salary           = filter_input(INPUT_POST, 'salary', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $status           = 'active';

        // 3. FILE UPLOAD CONFIGURATION
        $uploadBase = "../uploads/teachers/";
        $filePaths = [
            'photo' => null,
            'resume' => null,
            'doc_matric' => null,
            'doc_inter' => null,
            'doc_bachelors' => null,
            'doc_masters' => null,
            'doc_mphil' => null,
            'doc_phd' => null,
            'doc_experience' => null
        ];

        // Create folders if they don't exist
        if (!is_dir($uploadBase . "photos")) mkdir($uploadBase . "photos", 0755, true);
        if (!is_dir($uploadBase . "documents")) mkdir($uploadBase . "documents", 0755, true);

        // 4. UPLOAD LOGIC
        foreach ($filePaths as $key => $val) {
            if (isset($_FILES[$key]) && $_FILES[$key]['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES[$key]['tmp_name'];
                $fileName    = $_FILES[$key]['name'];
                $fileSize    = $_FILES[$key]['size'];
                $fileExt     = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                // Validate extension
                $allowedExts = ($key === 'photo') ? ['jpg', 'jpeg', 'png'] : ['pdf', 'doc', 'docx', 'jpg', 'png'];
                if (!in_array($fileExt, $allowedExts)) {
                    throw new Exception("Invalid file type for $key. Allowed: " . implode(', ', $allowedExts));
                }

                // Max 2MB limit
                if ($fileSize > 2097152) {
                    throw new Exception("File $key is too large. Max limit is 2MB.");
                }

                // Generate Path
                $subFolder   = ($key === 'photo') ? "photos/" : "documents/";
                $newFileName = $employee_id . "_" . $key . "_" . time() . "." . $fileExt;
                $destPath    = $uploadBase . $subFolder . $newFileName;

                if (move_uploaded_file($fileTmpPath, $destPath)) {
                    $filePaths[$key] = "uploads/teachers/" . $subFolder . $newFileName;
                }
            }
        }

        // 5. DATABASE INSERTION
        $sql = "INSERT INTO teachers (
                    user_id, employee_id, qualification, experience_years, gender, 
                    date_of_birth, phone, address, joining_date, salary, 
                    photo, resume, doc_matric, doc_inter, doc_bachelors, 
                    doc_masters, doc_mphil, doc_phd, doc_experience, status
                ) VALUES (
                    :user_id, :employee_id, :qualification, :experience_years, :gender, 
                    :dob, :phone, :address, :joining_date, :salary, 
                    :photo, :resume, :doc_matric, :doc_inter, :doc_bachelors, 
                    :doc_masters, :doc_mphil, :doc_phd, :doc_experience, :status
                )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':user_id'          => $user_id,
            ':employee_id'      => $employee_id,
            ':qualification'    => $qualification,
            ':experience_years' => $experience_years,
            ':gender'           => $gender,
            ':dob'              => $date_of_birth,
            ':phone'            => $phone,
            ':address'          => $address,
            ':joining_date'     => $joining_date,
            ':salary'           => $salary,
            ':photo'            => $filePaths['photo'],
            ':resume'           => $filePaths['resume'],
            ':doc_matric'       => $filePaths['doc_matric'],
            ':doc_inter'        => $filePaths['doc_inter'],
            ':doc_bachelors'    => $filePaths['doc_bachelors'],
            ':doc_masters'      => $filePaths['doc_masters'],
            ':doc_mphil'        => $filePaths['doc_mphil'],
            ':doc_phd'          => $filePaths['doc_phd'],
            ':doc_experience'   => $filePaths['doc_experience'],
            ':status'           => $status
        ]);

        // Commit everything
        $pdo->commit();

        $_SESSION['success'] = "Teacher successfully registered with ID: $employee_id";
        header("Location: ../frontend/admin/teachers.php");
        exit();
    } catch (Exception $e) {
        // Something went wrong! Roll back DB
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        // Cleanup: Delete uploaded files to save server space
        foreach ($filePaths as $path) {
            if ($path && file_exists("../" . $path)) {
                unlink("../" . $path);
            }
        }

        $_SESSION['error'] = "Registration failed: " . $e->getMessage();
        header("Location: ../frontend/admin/add_teacher.php");
        exit();
    }
}
