<?php
require_once 'config/db.php';

try {
    // Add resume_path column to applications table
    $sql = "ALTER TABLE applications ADD COLUMN resume_path VARCHAR(255) DEFAULT NULL AFTER cover_letter";
    $pdo->exec($sql);
    echo "Added resume_path column to applications table successfully.\n";
} catch (PDOException $e) {
    echo "Error updating table (column might already exist): " . $e->getMessage() . "\n";
}
?>
