<?php
require_once 'config/db.php';

try {
    $sql = "CREATE TABLE IF NOT EXISTS verification_requests (
        request_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        document_path VARCHAR(255) NOT NULL,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        admin_note TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $pdo->exec($sql);
    echo "Created 'verification_requests' table.\n";

} catch (PDOException $e) {
    echo "Schema Update Error: " . $e->getMessage();
}
?>
