<?php
// Database Configuration
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', 'Bipin7110@'); // Default XAMPP password is empty
define('DB_NAME', 'jbook_db');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    // Set the PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    // echo "Connected successfully"; 
} catch(PDOException $e) {
    // If the database doesn't exist, try to create it (for first time setup)
    if ($e->getCode() == 1049) { // Unknown database
        try {
            $pdo = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $sql = "CREATE DATABASE " . DB_NAME;
            $pdo->exec($sql);
            // echo "Database created successfully";
            
            // Reconnect to the new database
            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $ex) {
            die("ERROR: Could not connect or create database. " . $ex->getMessage());
        }
    } else {
        die("ERROR: Could not connect. " . $e->getMessage());
    }
}
?>
