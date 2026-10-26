<?php
/**
 * Database Migration Script
 * Run this once to add permissions column to users table
 */

require_once 'backend/config.php';

try {
    // Check if column already exists
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('permissions', $columns)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN permissions JSON DEFAULT NULL");
        echo "Successfully added 'permissions' column to users table!\n";
    } else {
        echo "Column 'permissions' already exists.\n";
    }
    
    echo "Migration completed successfully.\n";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
