<?php
require_once 'auth.php';
require_once 'config.php';
requireLogin();
requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = $_POST['student_id'];
    $payment_date = $_POST['payment_date'];
    $payment_method = $_POST['payment_method'];
    $receipt_no = $_POST['receipt_no'];
    
    // Arrays from the dynamic table
    $fee_ids = $_POST['fee_ids'] ?? [];
    $amounts = $_POST['amounts'] ?? [];

    if (empty($fee_ids)) {
        die("Error: No fees selected.");
    }

    try {
        $pdo->beginTransaction();

        $sql = "INSERT INTO fee_payments (
                    student_id, 
                    fee_structure_id, 
                    amount_paid, 
                    payment_date, 
                    payment_method, 
                    receipt_no, 
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, 'paid')";
        
        $stmt = $pdo->prepare($sql);

        // Loop through each fee added in the table
        foreach ($fee_ids as $index => $fee_id) {
            $amount = $amounts[$index];
            $stmt->execute([
                $student_id,
                $fee_id,
                $amount,
                $payment_date,
                $payment_method,
                $receipt_no
            ]);
        }

        $pdo->commit();
        
        // Redirect to a receipt view page with the receipt number
        header("Location: ../frontend/admin/receipt_view.php?receipt=" . urlencode($receipt_no));
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Transaction Failed: " . $e->getMessage());
    }
}