<?php
require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $account_id = intval($_GET['id']);

    try {
        // Drop ledger row using bound parameters
        $stmt = $pdo->prepare("DELETE FROM accounts WHERE id = :id");
        $stmt->execute([':id' => $account_id]);

        header("Location: accounts.php?status=deleted");
        exit;
    } catch (PDOException $e) {
        // Triggers gracefully if a foreign key relation constraint restricts the operation
        die("Structural Error: This accounting asset contains ledger historical entries or links and cannot be removed safely.");
    }
} else {
    header("Location: account.php");
    exit;
}