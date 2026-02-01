<?php
require_once 'config/db.php';

try {
    // Add 'message' column to notifications for storing context
    $stmt = $pdo->prepare("SHOW COLUMNS FROM notifications LIKE 'message'");
    $stmt->execute();
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE notifications ADD COLUMN message TEXT DEFAULT NULL AFTER type");
        echo "Added 'message' column to notifications.\n";
    } else {
        echo "'message' column already exists.\n";
    }

} catch (PDOException $e) {
    echo "Schema Update Error: " . $e->getMessage();
}
?>
