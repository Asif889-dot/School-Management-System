<?php
// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

require_once 'config.php';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $date = trim($_POST['selected_date'] ?? $_POST['date'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $eventType = trim($_POST['event_type'] ?? 'general');

    // Add type prefix to title if not already there
    if ($eventType == 'exam' && !preg_match('/exam/i', $title)) {
        $title = '[Exam] ' . $title;
    } elseif ($eventType == 'holiday' && !preg_match('/holiday/i', $title)) {
        $title = '[Holiday] ' . $title;
    } elseif ($eventType == 'meeting' && !preg_match('/meeting/i', $title)) {
        $title = '[Meeting] ' . $title;
    }

    // Validate inputs
    $errors = [];

    if (empty($title)) {
        $errors[] = "Event title is required";
    }

    if (empty($date)) {
        $errors[] = "Event date is required";
    } elseif (!strtotime($date)) {
        $errors[] = "Invalid date format";
    }

    // If no errors, insert into database
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO academic_calendar (event_title, event_date, description, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$title, $date, $description]);

            // Redirect back to calendar page with success message
            header('Location: ../frontend/admin/academic_calendar.php?success=1');
            exit();
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            header('Location: ../frontend/admin/academic_calendar.php?error=1');
            exit();
        }
    } else {
        $_SESSION['form_errors'] = $errors;
        header('Location: ../frontend/admin/academic_calendar.php?error=1');
        exit();
    }
} else {
    header('Location: ../frontend/admin/academic_calendar.php');
    exit();
}
