<?php
// config.php
session_start();

$DB_HOST = '127.0.0.1';
$DB_USER = 'root';       // change if different
$DB_PASS = '';           // change if you set a root password
$DB_NAME = 'myspace_clone';

try {
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (Exception $e) {
    die('DB Connect Error: ' . $e->getMessage());
}

function currentUserId() {
    return $_SESSION['user_id'] ?? null;
}

function currentUser() {
    global $pdo;
    $uid = currentUserId();
    if (!$uid) return null;
    $stmt = $pdo->prepare('SELECT id,username,display_name,profile_pic FROM users WHERE id = ?');
    $stmt->execute([$uid]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
?>