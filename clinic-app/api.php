<?php
// ─── AJAX API ─────────────────────────────────────────────────────────────────
// Endpoints (JSON):
// GET api.php?action=get_messages&last_id=N → new chat messages
// POST api.php?action=send_message → post body: message=TEXT
// GET api.php?action=get_members → members of current room

session_start();
require_once 'db.php';

header('Content-Type: application/json; charset=utf-8');

// Auth guard
if (empty($_SESSION['user_id'])) {
http_response_code(401);
echo json_encode(['error' => 'Unauthorized']);
exit;
}

$db = getDB();
$user = getUserById($db, (int)$_SESSION['user_id']);
if (!$user) {
http_response_code(401);
echo json_encode(['error' => 'User not found']);
exit;
}

// Touch last_seen on every API call (keeps online status fresh)
updateLastSeen($db, $user['id']);

$action = isset($_GET['action']) ? $_GET['action']
: (isset($_POST['action']) ? $_POST['action'] : '');

// ── GET MESSAGES ──────────────────────────────────────────────────────────────
if ($action === 'get_messages') {
$lastId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
$s = $db->prepare(
"SELECT id, user_id, sender_name, message, created_at
FROM messages
WHERE room_id = ? AND id > ?
ORDER BY id ASC
LIMIT 80"
);
$s->execute([$user['room_id'], $lastId]);
$msgs = $s->fetchAll();

echo json_encode([
'messages' => $msgs,
'current_uid' => (int)$user['id']
]);
exit;
}

// ── SEND MESSAGE ──────────────────────────────────────────────────────────────
if ($action === 'send_message' && $_SERVER['REQUEST_METHOD'] === 'POST') {
$text = trim(isset($_POST['message']) ? $_POST['message'] : '');
if ($text === '') {
echo json_encode(['error' => 'Empty message']);
exit;
}
if (mb_strlen($text) > 2000) {
$text = mb_substr($text, 0, 2000);
}

$s = $db->prepare(
"INSERT INTO messages (room_id, user_id, sender_name, message)
VALUES (?, ?, ?, ?)"
);
$s->execute([$user['room_id'], $user['id'], $user['name'], $text]);

echo json_encode(['ok' => true, 'id' => (int)$db->lastInsertId()]);
exit;
}

// ── GET MEMBERS ───────────────────────────────────────────────────────────────
if ($action === 'get_members') {
$members = getMembersByRoom($db, $user['room_id']);
$result = array();
foreach ($members as $m) {
$result[] = array(
'id' => (int)$m['id'],
'name' => $m['name'],
'email' => $m['email'],
'role' => $m['role'],
'online' => isOnline($m['last_seen']),
'joined' => $m['created_at']
);
}
echo json_encode(['members' => $result]);
exit;
}

http_response_code(400);
echo json_encode(['error' => 'Unknown action']);
