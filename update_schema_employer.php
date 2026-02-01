<?php
require_once 'config/db.php';

try {
    // 1. Applications: Update status enum
    // Note: In MySQL, modifying ENUM requires restating all values.
    try {
        $pdo->exec("ALTER TABLE applications MODIFY COLUMN status ENUM('pending', 'reviewed', 'shortlisted', 'interviewing', 'rejected', 'accepted') DEFAULT 'pending'");
        echo "Updated applications status enum.\n";
    } catch (Exception $e) { echo "Applications status update failed: " . $e->getMessage() . "\n"; }

    // 2. Jobs: Update status enum and add is_featured
    try {
        // First check if 'paused' exists or just force update
        $pdo->exec("ALTER TABLE jobs MODIFY COLUMN status ENUM('pending', 'active', 'closed', 'paused') DEFAULT 'pending'");
        echo "Updated jobs status enum.\n";
    } catch (Exception $e) { echo "Jobs status update failed: " . $e->getMessage() . "\n"; }

    try {
        $pdo->exec("ALTER TABLE jobs ADD COLUMN is_featured TINYINT(1) DEFAULT 0");
        echo "Added is_featured to jobs.\n";
    } catch (Exception $e) { echo "is_featured might exist.\n"; }

    // 3. Profile Views Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS profile_views (
        view_id INT AUTO_INCREMENT PRIMARY KEY,
        viewer_id INT NOT NULL,
        profile_owner_id INT NOT NULL,
        viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (viewer_id) REFERENCES users(user_id) ON DELETE CASCADE,
        FOREIGN KEY (profile_owner_id) REFERENCES users(user_id) ON DELETE CASCADE
    )");
    echo "Created profile_views table.\n";
    
    // Seed some views
    $stmt = $pdo->query("SELECT user_id FROM users WHERE user_type='employer' LIMIT 1");
    $emp = $stmt->fetch();
    if ($emp) {
        $pdo->exec("INSERT INTO profile_views (viewer_id, profile_owner_id) SELECT user_id, " . $emp['user_id'] . " FROM users WHERE user_type='seeker' LIMIT 5");
        echo "Seeded profile views.\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
