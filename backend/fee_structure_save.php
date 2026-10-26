<?php
session_start();
require_once 'auth.php';
require_once 'config.php';

// Access Control
requireLogin();
requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and collect inputs
    $academic_year_id = filter_input(INPUT_POST, 'academic_year_id', FILTER_SANITIZE_NUMBER_INT);
    $class_id         = filter_input(INPUT_POST, 'class_id', FILTER_SANITIZE_NUMBER_INT);
    $fee_type_id      = filter_input(INPUT_POST, 'fee_type_id', FILTER_SANITIZE_NUMBER_INT);
    $amount           = filter_input(INPUT_POST, 'amount', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $frequency        = $_POST['frequency'] ?? 'monthly';

    try {
        // 1. Check if this fee structure already exists for this class/session
        $checkStmt = $pdo->prepare("SELECT id FROM fee_structures 
                                    WHERE academic_year_id = ? 
                                    AND class_id = ? 
                                    AND fee_type_id = ?");
        $checkStmt->execute([$academic_year_id, $class_id, $fee_type_id]);

        if ($checkStmt->rowCount() > 0) {
            $_SESSION['fee_message'] = "Error: This fee type is already defined for the selected class and session.";
            $_SESSION['fee_message_type'] = "danger";
        } else {
            // 2. Insert new fee structure
            $sql = "INSERT INTO fee_structures (academic_year_id, class_id, fee_type_id, amount, frequency) 
                    VALUES (:ayid, :cid, :ftid, :amt, :freq)";

            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([
                ':ayid' => $academic_year_id,
                ':cid'  => $class_id,
                ':ftid' => $fee_type_id,
                ':amt'  => $amount,
                ':freq' => $frequency
            ]);

            if ($result) {
                $_SESSION['fee_message'] = "Fee structure created successfully!";
                $_SESSION['fee_message_type'] = "success";
            } else {
                throw new Exception("Failed to insert record.");
            }
        }
    } catch (Exception $e) {
        $_SESSION['fee_message'] = "System Error: " . $e->getMessage();
        $_SESSION['fee_message_type'] = "danger";
    }

    // Redirect back to the setup page
    header("Location: ../frontend/admin/fee_setup.php");
    exit();
}
