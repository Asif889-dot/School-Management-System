<?php
/**
 * Asynchronous Subject Mapping API Endpoint
 * Path: /frontend/admin/get_subjects_by_class.php
 * School Management System - Smart Choice Academy
 */

// 1. Force response headers to return standard JSON data structure
header('Content-Type: application/json; charset=utf-8');

// 2. Suppress raw HTML error reporting output to avoid breaking AJAX token readers
ini_set('display_errors', 0);
error_reporting(E_ALL);

// 3. Absolute dynamic file boundaries inclusions 
require_once 'auth.php';
require_once 'config.php';

// Safe check: Initialize user session access boundaries securely if missing
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 4. Session Protection Layer Checklist
if (!isset($_SESSION['role'])) {
    echo json_encode([
        'success' => false, 
        'subjects' => [], 
        'message' => 'Unauthorized system access. Session has expired.'
    ]);
    exit;
}

// 5. Map and Verify the Database Engine Connection Instance Handle
if (isset($pdo) && $pdo instanceof PDO) {
    $db = $pdo;
} elseif (isset($conn) && $conn instanceof PDO) {
    $db = $conn;
} else {
    echo json_encode([
        'success' => false, 
        'subjects' => [], 
        'message' => 'Core Database instance linkage variable ($pdo or $conn) missing.'
    ]);
    exit;
}

// 6. Sanitize inbound payload tracking indices
$class_id = (int)($_GET['class_id'] ?? 0);

if ($class_id <= 0) {
    echo json_encode([
        'success' => false, 
        'subjects' => [], 
        'message' => 'Invalid class validation structure criteria context.'
    ]);
    exit;
}

// 7. Query and Build Output Array
try {
    /**
     * SMART CHOICE ACADEMY SCHEMA MATCH:
     * Pulls unique course names belonging directly to a given class_id from the 'courses' table.
     * Alias 'course_name' to 'subject_name' to match the frontend JavaScript.
     */
    $query = "SELECT id, course_name AS subject_name 
              FROM courses 
              WHERE class_id = ? 
              ORDER BY course_name ASC";
              
    $stmt = $db->prepare($query);
    $stmt->execute([$class_id]);
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return exact signature shape parsing requirements to frontend matrix
    echo json_encode([
        'success' => true,
        'subjects' => $subjects
    ]);
    exit;

} catch (PDOException $e) {
    // Graceful error processing capture strategy if table engine execution breaks
    echo json_encode([
        'success' => false,
        'subjects' => [],
        'message' => 'SQL Data Registry Error: ' . $e->getMessage()
    ]);
    exit;
}