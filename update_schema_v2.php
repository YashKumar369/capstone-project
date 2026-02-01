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
    // 1. Applications: Add cover_letter_path
    if (!columnExists($pdo, 'applications', 'cover_letter_path')) {
        $pdo->exec("ALTER TABLE applications ADD COLUMN cover_letter_path VARCHAR(255) DEFAULT NULL");
        echo "Added cover_letter_path to applications.\n";
    }

    // 2. Jobs: Add currency
    if (!columnExists($pdo, 'jobs', 'currency')) {
        $pdo->exec("ALTER TABLE jobs ADD COLUMN currency VARCHAR(10) DEFAULT 'USD'");
        echo "Added currency to jobs.\n";
    }

    // 3. Jobs: Add is_featured (if not exists)
    if (!columnExists($pdo, 'jobs', 'is_featured')) {
        $pdo->exec("ALTER TABLE jobs ADD COLUMN is_featured TINYINT(1) DEFAULT 0");
        echo "Added is_featured to jobs.\n";
    }
    
    // 4. Jobs: Add salary (numeric) if we want to separate from salary_range text
    // User asked for "salary amount should always be in numbers". 
    // Let's rely on 'salary_range' being the column but we will enforce numeric storage or add 'salary' column.
    // Adding 'salary' column for cleaner logic.
    if (!columnExists($pdo, 'jobs', 'salary')) {
        $pdo->exec("ALTER TABLE jobs ADD COLUMN salary DECIMAL(10,2) DEFAULT NULL");
        echo "Added salary to jobs.\n";
    }

    echo "Schema update completed successfully.";

} catch (PDOException $e) {
    echo "Schema Update Error: " . $e->getMessage();
}
?>
