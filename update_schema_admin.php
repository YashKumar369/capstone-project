<?php
require_once 'config/db.php';

try {
    // 1. Users: Add status
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN status ENUM('active','banned','suspended') DEFAULT 'active'");
        echo "Added status column to users.\n";
    } catch (Exception $e) { echo "Users status column might exist.\n"; }

    // 2. Jobs: Add expires_at, tags
    try {
        $pdo->exec("ALTER TABLE jobs ADD COLUMN expires_at DATETIME DEFAULT NULL");
        $pdo->exec("ALTER TABLE jobs ADD COLUMN tags TEXT DEFAULT NULL");
        echo "Added columns to jobs.\n";
    } catch (Exception $e) { echo "Jobs columns might exist.\n"; }

    // 3. Reports Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS reports (
        report_id INT AUTO_INCREMENT PRIMARY KEY,
        reporter_id INT NOT NULL,
        target_type ENUM('post', 'comment', 'user', 'job') NOT NULL,
        target_id INT NOT NULL,
        reason TEXT,
        status ENUM('pending', 'resolved', 'dismissed') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (reporter_id) REFERENCES users(user_id) ON DELETE CASCADE
    )");
    echo "Created reports table.\n";

    // 4. System Settings Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
        setting_key VARCHAR(50) PRIMARY KEY,
        setting_value TEXT
    )");
    echo "Created system_settings table.\n";

    // Seed Settings
    $pdo->exec("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES 
        ('email_approval_template', 'Your job has been approved!'),
        ('ad_placement_sidebar', '<div>Ad Placeholder</div>')
    ");
    echo "Seeded settings.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
