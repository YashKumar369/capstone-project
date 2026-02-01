<?php
require_once 'config/db.php';

try {
    $pdo->exec("ALTER TABLE users ADD COLUMN ban_reason TEXT DEFAULT NULL");
    echo "Added ban_reason column to users table.<br>";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), "Duplicate column name") !== false) {
        echo "Column ban_reason already exists.<br>";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
?>
