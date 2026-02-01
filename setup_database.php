<?php
require_once 'config/db.php';

try {
    // Read the SQL file
    $sql = file_get_contents('database.sql');
    
    // Execute the SQL commands
    $pdo->exec($sql);
    
    echo "Database tables created successfully from database.sql!\n";
    
} catch (PDOException $e) {
    die("ERROR: Could not execute SQL script. " . $e->getMessage());
}
?>
