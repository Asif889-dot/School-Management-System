<?php
require_once 'config.php';
require_once 'auth.php';
requireLogin();

$q = $_GET['q'] ?? '';

if (strlen($q) >= 2) {
    // Search by Reg ID or Name
    $stmt = $pdo->prepare("
        SELECT s.id, s.reg_id, s.first_name, s.last_name, c.class_name 
        FROM students s 
        JOIN classes c ON s.class_id = c.id 
        WHERE s.reg_id LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? 
        LIMIT 8
    ");
    $searchTerm = "%$q%";
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $students = $stmt->fetchAll();

    if ($students) {
        foreach ($students as $s) {
            echo '<button type="button" class="list-group-item list-group-item-action py-2" 
                    onclick="selectStudent(\''.$s['id'].'\', \''.$s['reg_id'].' - '.$s['first_name'].' '.$s['last_name'].'\')">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="fw-bold">'.$s['first_name'].' '.$s['last_name'].'</span><br>
                            <small class="text-muted">ID: '.$s['reg_id'].'</small>
                        </div>
                        <span class="badge bg-light text-dark border">'.$s['class_name'].'</span>
                    </div>
                  </button>';
        }
    } else {
        echo '<div class="list-group-item text-muted">No student found.</div>';
    }
}