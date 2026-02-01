<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$notif_id = $_GET['id'];
$redirect_url = isset($_GET['url']) ? $_GET['url'] : 'notifications.php';

// Mark as read
$stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND recipient_id = ?");
$stmt->execute([$notif_id, $_SESSION['user_id']]);

header("Location: " . $redirect_url);
exit;
