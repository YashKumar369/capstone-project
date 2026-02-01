<?php
require_once 'config/db.php';

try {
    echo "Adding social ID columns to users table...\n";
    
    // Add columns if they don't exist
    $search = $pdo->query("SHOW COLUMNS FROM users LIKE 'google_id'");
    if ($search->rowCount() == 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN google_id VARCHAR(255) NULL");
        echo "Added google_id.\n";
    }

    $search = $pdo->query("SHOW COLUMNS FROM users LIKE 'facebook_id'");
    if ($search->rowCount() == 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN facebook_id VARCHAR(255) NULL");
        echo "Added facebook_id.\n";
    }
    
    $search = $pdo->query("SHOW COLUMNS FROM users LIKE 'apple_id'");
    if ($search->rowCount() == 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN apple_id VARCHAR(255) NULL");
        echo "Added apple_id.\n";
    }

    echo "Schema update complete.";

} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}
