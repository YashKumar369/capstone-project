<?php
require_once 'config/db.php';

header('Content-Type: application/json');

$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

try {
    // Select distinct titles that match the query
    $stmt = $pdo->prepare("SELECT DISTINCT title FROM jobs WHERE title LIKE ? AND status = 'active' ORDER BY title ASC LIMIT 5");
    $stmt->execute(["%$query%"]);
    $titles = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode($titles);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
