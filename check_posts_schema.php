<?php
require_once 'config/db.php';

try {
    $stmt = $pdo->query("DESCRIBE posts");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Columns in posts table:\n";
    print_r($columns);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
