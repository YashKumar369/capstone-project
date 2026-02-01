<?php
require_once 'config/db.php';

function columnExists($pdo, $table, $column) {
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM $table LIKE ?");
        $stmt->execute([$column]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

try {
    // 1. Users: Add 'status' if missing
    if (!columnExists($pdo, 'users', 'status')) {
        $pdo->exec("ALTER TABLE users ADD COLUMN status ENUM('active', 'banned', 'suspended') DEFAULT 'active'");
        echo "Added status to users.\n";
    }

    // 2. Users: Add 'is_verified'
    if (!columnExists($pdo, 'users', 'is_verified')) {
        $pdo->exec("ALTER TABLE users ADD COLUMN is_verified TINYINT(1) DEFAULT 0");
        echo "Added is_verified to users.\n";
    }

    // 3. Notifications: Check for columns if table exists
    // The previous CREATE TABLE IF NOT EXISTS didn't fix existing tables.
    
    // Check 'type'
    if (!columnExists($pdo, 'notifications', 'type')) {
        $pdo->exec("ALTER TABLE notifications ADD COLUMN type VARCHAR(50) NOT NULL AFTER actor_id");
        echo "Added type to notifications.\n";
    }

    // Check 'reference_id'
    if (!columnExists($pdo, 'notifications', 'reference_id')) {
        $pdo->exec("ALTER TABLE notifications ADD COLUMN reference_id INT DEFAULT NULL AFTER type");
        echo "Added reference_id to notifications.\n";
    }

    echo "Schema update complete.\n";

} catch (PDOException $e) {
    echo "Schema Update Error: " . $e->getMessage();
}
?>
