<?php
require_once 'auth.php';
require_once 'config.php';
requireLogin();

// Check if a valid ID was passed in the query parameter
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $book_id = intval($_GET['id']);

    try {
        // Securely delete the book using PDO bound parameters
        $stmt = $pdo->prepare("DELETE FROM books WHERE id = :id");
        $stmt->execute([':id' => $book_id]);

        // Redirect back to inventory upon smooth execution
        header("Location: ../frontend/admin/books.php?status=deleted");
        exit;
    } catch (PDOException $e) {
        // If an explicit database restriction drops, capture it gracefully
        die("Error deleting structural inventory asset: " . $e->getMessage());
    }
} else {
    // If the file is accessed directly without an explicit ID parameter, push back to safety
    header("Location: ../frontend/admin/books.php");
    exit;
}