<?php
require_once 'config.php';
$mode = $_GET['mode'] ?? 'student';

if ($mode == 'student') {
    $data = $pdo->query("SELECT id, CONCAT(first_name, ' ', last_name) as name FROM students")->fetchAll(PDO::FETCH_ASSOC);
} elseif ($mode == 'section') {
    $data = $pdo->query("SELECT id, section_name as name FROM sections")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $data = $pdo->query("SELECT id, class_name as name FROM classes")->fetchAll(PDO::FETCH_ASSOC);
}
header('Content-Type: application/json');
echo json_encode($data);
?>