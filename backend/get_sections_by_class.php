<?php
// backend/get_sections_by_class.php
header('Content-Type: application/json');
require_once 'auth.php';
require_once 'config.php';

$class_id = (int)($_GET['class_id'] ?? 0);
if ($class_id <= 0) {
    echo json_encode(['success' => false, 'sections' => []]);
    exit;
}

$stmt = $pdo->prepare("SELECT id, section_name FROM sections WHERE class_id = ? ORDER BY section_name ASC");
$stmt->execute([$class_id]);
$sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'sections' => $sections]);