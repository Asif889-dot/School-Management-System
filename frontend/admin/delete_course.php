<?php
require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();

// Validate the presence of the unique identifier key parameters
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $course_id = intval($_GET['id']);

    try {
        // Securely drop the course row using a parameterized PDO context
        $stmt = $pdo->prepare("DELETE FROM courses WHERE id = :id");
        $stmt->execute([':id' => $course_id]);

        // Return gracefully to the data matrix layout views with the success flag
        header("Location: courses.php?status=deleted");
        exit;
    } catch (PDOException $e) {
        // Halt and display structural failure details if a foreign key dependency or system issue breaks
        die("System Curriculum Record Processing Failure: " . $e->getMessage());
    }
} else {
    // Fall back to safety if accessed cleanly without passing an internal identity node
    header("Location: courses.php");
    exit;
}