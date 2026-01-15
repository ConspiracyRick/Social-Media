<?php
require 'config.php';
$action = $_GET['action'] ?? null;
header('Content-Type: application/json');

if ($action === 'register') {
    $data = json_decode(file_get_contents('php://input'), true);
    $username = trim($data['username'] ?? '');
    $email = trim($data['email'] ?? '');
    $password = $data['password'] ?? '';

    if (!$username || !$email || !$password) {
        http_response_code(400); echo json_encode(['error'=>'Missing fields']); exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400); echo json_encode(['error'=>'Invalid email']); exit;
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username,email,password_hash,display_name) VALUES (?,?,?,?)");
    try {
        $stmt->execute([$username,$email,$hash,$username]);
        $_SESSION['user_id'] = $pdo->lastInsertId();
        echo json_encode(['ok'=>true,'user_id'=>$_SESSION['user_id']]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error'=>'Could not register: '.$e->getMessage()]);
    }
    exit;
}

if ($action === 'login') {
    $data = json_decode(file_get_contents('php://input'), true);
    $username = trim($data['username'] ?? '');
    $password = $data['password'] ?? '';
    if (!$username || !$password) { http_response_code(400); echo json_encode(['error'=>'Missing']); exit; }
    $stmt = $pdo->prepare("SELECT id,password_hash FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username,$username]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($u && password_verify($password, $u['password_hash'])) {
        $_SESSION['user_id'] = $u['id'];
        echo json_encode(['ok'=>true,'user_id'=>$u['id']]);
    } else {
        http_response_code(401);
        echo json_encode(['error'=>'Invalid credentials']);
    }
    exit;
}

if ($action === 'logout') {
    session_destroy();
    echo json_encode(['ok'=>true]);
    exit;
}

http_response_code(400);
echo json_encode(['error'=>'No action']);
?>