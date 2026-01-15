<?php
require 'config.php';
header('Content-Type: application/json');
$action = $_GET['action'] ?? null;

function json_error($msg, $code=400){ http_response_code($code); echo json_encode(['error'=>$msg]); exit; }

if ($action === 'get_profile') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) json_error('No id',404);
    $stmt = $pdo->prepare("SELECT id,username,display_name,bio,profile_pic,bg_image,custom_css FROM users WHERE id=?");
    $stmt->execute([$id]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$u) json_error('User not found',404);
    // top friends (accepted)
    $fstmt = $pdo->prepare("SELECT u.id,u.username,u.display_name,u.profile_pic FROM friend_requests fr JOIN users u ON ( (fr.requester_id = u.id AND fr.receiver_id = ?) OR (fr.receiver_id = u.id AND fr.requester_id = ?) ) WHERE fr.status = 1 AND u.id != ? LIMIT 8");
    $fstmt->execute([$id,$id,$id]);
    $u['top_friends'] = $fstmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($u);
    exit;
}

if ($action === 'post_status') {
    $uid = currentUserId(); if (!$uid) json_error('login',401);
    $data = json_decode(file_get_contents('php://input'), true);
    $profile_user_id = (int)($data['profile_user_id'] ?? $uid);
    $content = trim($data['content'] ?? '');
    if (!$content) json_error('Empty');
    $stmt = $pdo->prepare("INSERT INTO statuses (user_id,profile_user_id,content) VALUES (?,?,?)");
    $stmt->execute([$uid,$profile_user_id,$content]);
    echo json_encode(['ok'=>true,'status_id'=>$pdo->lastInsertId()]);
    exit;
}

if ($action === 'get_feed') {
    $profile_user_id = (int)($_GET['profile_user_id'] ?? 0);
    $limit = min(50,(int)($_GET['limit'] ?? 20));
    $stmt = $pdo->prepare("SELECT s.*, u.username, u.display_name, u.profile_pic FROM statuses s JOIN users u ON u.id=s.user_id WHERE s.profile_user_id = ? ORDER BY s.created_at DESC LIMIT ?");
    $stmt->execute([$profile_user_id,$limit]);
    $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($statuses as &$st) {
        $cstmt = $pdo->prepare("SELECT c.*, u.username, u.display_name, u.profile_pic FROM comments c JOIN users u ON u.id=c.user_id WHERE c.status_id = ? ORDER BY c.created_at ASC");
        $cstmt->execute([$st['id']]);
        $st['comments'] = $cstmt->fetchAll(PDO::FETCH_ASSOC);
    }
    echo json_encode($statuses);
    exit;
}

if ($action === 'post_comment') {
    $uid = currentUserId(); if (!$uid) json_error('login',401);
    $data = json_decode(file_get_contents('php://input'), true);
    $status_id = (int)$data['status_id'];
    $comment = trim($data['comment'] ?? '');
    if (!$comment) json_error('Empty');
    $stmt = $pdo->prepare("INSERT INTO comments (status_id,user_id,comment) VALUES (?,?,?)");
    $stmt->execute([$status_id,$uid,$comment]);
    echo json_encode(['ok'=>true,'comment_id'=>$pdo->lastInsertId()]);
    exit;
}

// friend requests
if ($action === 'send_friend_request') {
    $uid = currentUserId(); if (!$uid) json_error('login',401);
    $data = json_decode(file_get_contents('php://input'), true);
    $to = (int)($data['to'] ?? 0);
    if (!$to || $to == $uid) json_error('Bad target');
    $stmt = $pdo->prepare("INSERT INTO friend_requests (requester_id,receiver_id) VALUES (?,?)");
    try { $stmt->execute([$uid,$to]); echo json_encode(['ok'=>true]); } catch(Exception $e){ json_error('Could not send'); }
    exit;
}
if ($action === 'respond_friend_request') {
    $uid = currentUserId(); if (!$uid) json_error('login',401);
    $data = json_decode(file_get_contents('php://input'), true);
    $req_id = (int)$data['request_id'];
    $status = (int)($data['status'] ?? 2); // 1 accept,2 reject
    $stmt = $pdo->prepare("SELECT * FROM friend_requests WHERE id = ? AND receiver_id = ?");
    $stmt->execute([$req_id,$uid]); $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$r) json_error('Not found',404);
    $ustmt = $pdo->prepare("UPDATE friend_requests SET status = ? WHERE id = ?");
    $ustmt->execute([$status,$req_id]);
    echo json_encode(['ok'=>true]);
    exit;
}

// messages
if ($action === 'send_message') {
    $uid = currentUserId(); if (!$uid) json_error('login',401);
    $data = json_decode(file_get_contents('php://input'), true);
    $to = (int)($data['to'] ?? 0);
    $subject = trim($data['subject'] ?? '');
    $body = trim($data['body'] ?? '');
    if (!$to || !$body) json_error('Missing');
    $stmt = $pdo->prepare("INSERT INTO messages (sender_id,receiver_id,subject,body) VALUES (?,?,?,?)");
    $stmt->execute([$uid,$to,$subject,$body]);
    echo json_encode(['ok'=>true,'message_id'=>$pdo->lastInsertId()]);
    exit;
}

if ($action === 'inbox') {
    $uid = currentUserId(); if (!$uid) json_error('login',401);
    $stmt = $pdo->prepare("SELECT m.*, u.username AS from_username, u.display_name AS from_name FROM messages m JOIN users u ON u.id = m.sender_id WHERE m.receiver_id = ? ORDER BY m.created_at DESC");
    $stmt->execute([$uid]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

if ($action === 'outbox') {
    $uid = currentUserId(); if (!$uid) json_error('login',401);
    $stmt = $pdo->prepare("SELECT m.*, u.username AS to_username, u.display_name AS to_name FROM messages m JOIN users u ON u.id = m.receiver_id WHERE m.sender_id = ? ORDER BY m.created_at DESC");
    $stmt->execute([$uid]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// upload handler (profile pic / bg / music)
if ($action === 'upload') {
    $uid = currentUserId(); if (!$uid) json_error('login',401);
    if (!isset($_FILES['file'])) json_error('No file');
    $type = $_POST['type'] ?? 'other';
    $allowed_images = ['image/jpeg','image/png','image/gif'];
    $allowed_audio = ['audio/mpeg','audio/mp3'];
    $f = $_FILES['file'];
    if ($f['error'] !== UPLOAD_ERR_OK) json_error('Upload error');
    if ($type === 'profile' || $type === 'bg') {
        if (!in_array($f['type'],$allowed_images)) json_error('Bad file type');
        $ext = pathinfo($f['name'], PATHINFO_EXTENSION);
        $target = 'uploads/' . uniqid() . '.' . $ext;
        if (!move_uploaded_file($f['tmp_name'], $target)) json_error('Move failed');
        if ($type === 'profile') {
            $stmt = $pdo->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
            $stmt->execute([$target,$uid]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET bg_image = ? WHERE id = ?");
            $stmt->execute([$target,$uid]);
        }
        echo json_encode(['ok'=>true,'path'=>$target]);
        exit;
    } elseif ($type === 'music') {
        if (!in_array($f['type'],$allowed_audio)) json_error('Bad audio type');
        $ext = pathinfo($f['name'], PATHINFO_EXTENSION);
        $target = 'uploads/' . uniqid() . '.' . $ext;
        if (!move_uploaded_file($f['tmp_name'], $target)) json_error('Move failed');
        echo json_encode(['ok'=>true,'path'=>$target]);
        exit;
    } else {
        json_error('Unknown type');
    }
}

if ($action === 'save_custom_css') {
    $uid = currentUserId(); if (!$uid) json_error('login',401);
    $data = json_decode(file_get_contents('php://input'), true);
    $css = substr($data['css'] ?? '', 0, 2000);
    // naive sanitation: strip html/script; and only allow safe-ish properties using whitelist (server-side)
    $allowed = ['color','background','background-color','background-image','font-family','font-size','text-align','margin','padding','border','border-radius','width','height','float','clear','display'];
    // allow only simple selectors and properties (very basic)
    // For demo purposes we simply remove '<' and '>' to avoid tags and store CSS.
    $css = str_replace(['<','>','<?','?>'],'', $css);
    $stmt = $pdo->prepare("UPDATE users SET custom_css = ? WHERE id = ?");
    $stmt->execute([$css,$uid]);
    echo json_encode(['ok'=>true]);
    exit;
}

json_error('Bad request');
?>