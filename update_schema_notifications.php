<?php
require_once 'config/db.php';

try {
    // Notifications Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        notification_id INT AUTO_INCREMENT PRIMARY KEY,
        recipient_id INT NOT NULL,
        actor_id INT NOT NULL,
        type ENUM('new_follow', 'follow_accepted', 'new_job', 'like', 'comment') NOT NULL,
        reference_id INT DEFAULT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (recipient_id) REFERENCES users(user_id) ON DELETE CASCADE,
        FOREIGN KEY (actor_id) REFERENCES users(user_id) ON DELETE CASCADE
    )");
    echo "Created notifications table.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
