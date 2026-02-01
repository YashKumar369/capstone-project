<?php
require_once 'config/db.php';

try {
    echo "Attempting to modify notifications.type column...\n";
    // Force modify to VARCHAR(100) to allow any string type and fix the ENUM truncation issue
    $sql = "ALTER TABLE notifications MODIFY COLUMN type VARCHAR(100) NOT NULL";
    $pdo->exec($sql);
    echo "Successfully changed 'type' column to VARCHAR(100).\n";
} catch (PDOException $e) {
    echo "Error modifying table: " . $e->getMessage() . "\n";
}
?>
