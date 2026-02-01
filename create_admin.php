<?php
require_once 'config/db.php';

$username = 'admin';
$email = 'admin@jbook.com';
$password = 'admin123';
$hashed_password = password_hash($password, PASSWORD_BCRYPT);
$user_type = 'admin';

try {
    // Check if admin exists
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->rowCount() > 0) {
        echo "Admin user already exists.\n";
    } else {
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, user_type) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$username, $email, $hashed_password, $user_type])) {
            echo "Admin user created successfully!\n";
            echo "Email: $email\n";
            echo "Password: $password\n";
        } else {
             echo "Failed to create admin user.\n";
        }
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
