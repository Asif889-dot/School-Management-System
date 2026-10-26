<?php

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once 'auth.php';
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'sections' => [], 'subjects' => [], 'message' => 'Unauthorized access.']);
    exit;
}

$db = $pdo;

// Determine teacher_id
$user_id = $_SESSION['user_id'] ?? null;
$teacher_id = null;

if ($user_id) {
    $stmt = $db->prepare("SELECT teacher_id FROM teachers WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $teacher_id = $stmt->fetchColumn();
}

if (!$teacher_id && isset($_SESSION['reg_id'])) {
    $stmt = $db->prepare("SELECT teacher_id FROM teachers WHERE employee_id = ?");
    $stmt->execute([$_SESSION['reg_id']]);
    $teacher_id = $stmt->fetchColumn();
}

if (!$teacher_id) {
    echo json_encode(['success' => false, 'sections' => [], 'subjects' => [], 'message' => 'Teacher record not found.']);
    exit;
}

// Current academic year
$stmt = $db->prepare("SELECT id FROM academic_years WHERE is_current = 'yes' AND status = 'active' LIMIT 1");
$stmt->execute();
$academic_year_id = $stmt->fetchColumn() ?: 1;

$class_id = (int)($_GET['class_id'] ?? 0);
$section_id = (int)($_GET['section_id'] ?? 0);

if ($class_id <= 0) {
    echo json_encode(['success' => false, 'sections' => [], 'subjects' => [], 'message' => 'Invalid class.']);
    exit;
}

try {
    // Sections taught by this teacher for this class
    $sections = [];
    $stmt = $db->prepare("
        SELECT DISTINCT sec.id, sec.section_name
        FROM timetable_records tr
        JOIN sections sec ON tr.section_id = sec.id
        WHERE tr.teacher_id = ? AND tr.academic_year_id = ? AND tr.class_id = ?
        ORDER BY sec.section_name ASC
    ");
    $stmt->execute([$teacher_id, $academic_year_id, $class_id]);
    $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Subjects taught by this teacher for this class (and optionally section)
    $subjects = [];
    if ($section_id > 0) {
        $stmt = $db->prepare("
            SELECT DISTINCT cr.id, cr.course_name AS subject_name
            FROM timetable_records tr
            JOIN courses cr ON tr.course_id = cr.id
            WHERE tr.teacher_id = ? AND tr.academic_year_id = ? AND tr.class_id = ? AND tr.section_id = ?
            ORDER BY cr.course_name ASC
        ");
        $stmt->execute([$teacher_id, $academic_year_id, $class_id, $section_id]);
        $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $db->prepare("
            SELECT DISTINCT cr.id, cr.course_name AS subject_name
            FROM timetable_records tr
            JOIN courses cr ON tr.course_id = cr.id
            WHERE tr.teacher_id = ? AND tr.academic_year_id = ? AND tr.class_id = ?
            ORDER BY cr.course_name ASC
        ");
        $stmt->execute([$teacher_id, $academic_year_id, $class_id]);
        $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        'success' => true,
        'sections' => $sections,
        'subjects' => $subjects
    ]);
    exit;
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'sections' => [], 'subjects' => [], 'message' => 'SQL error: ' . $e->getMessage()]);
    exit;
}
