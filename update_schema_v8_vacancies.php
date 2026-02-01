<?php
require_once 'config/db.php';

try {
    $pdo->exec("ALTER TABLE jobs ADD COLUMN vacancies INT DEFAULT 1");
    echo "Added vacancies column to jobs table.<br>";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), "Duplicate column name") !== false) {
        echo "Column vacancies already exists.<br>";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
?>
