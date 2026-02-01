<?php
require_once 'config/db.php';

try {
    // 1. Create Reports Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS reports (
        report_id INT AUTO_INCREMENT PRIMARY KEY,
        reporter_id INT NOT NULL,
        target_type ENUM('post', 'job', 'user') NOT NULL,
        target_id INT NOT NULL,
        reason TEXT NOT NULL,
        status ENUM('pending', 'resolved', 'dismissed') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (reporter_id) REFERENCES users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "Reports table created/verified.\n";
    
    // 2. Add System Settings Table (if referenced in Admin but missing, though user wants Config tab removed)
    // admin.php references `system_settings`. Without it, page might error on fetch?
    // "SELECT * FROM system_settings".
    // I will CREATE it to be safe, even if I remove the tab, the fetch logic might remain or I should remove the fetch logic too.
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
        setting_key VARCHAR(50) PRIMARY KEY,
        setting_value TEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "System Settings table created/verified.\n";

} catch (PDOException $e) {
    echo "Schema Update Error: " . $e->getMessage();
}
?>
