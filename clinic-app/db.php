<?php
// ─── Database layer ───────────────────────────────────────────────────────────
// Uses SQLite stored in /data/clinic.db (auto-created on first run).
// Compatible with PHP 5.5+

function getDB() {
static $db = null;
if ($db !== null) return $db;

$dir = __DIR__ . '/data';
if (!is_dir($dir)) {
mkdir($dir, 0755, true);
}
$db = new PDO('sqlite:' . $dir . '/clinic.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$db->exec("PRAGMA journal_mode=WAL");
initSchema($db);
return $db;
}

function initSchema($db) {
$db->exec("
CREATE TABLE IF NOT EXISTS rooms (
id INTEGER PRIMARY KEY AUTOINCREMENT,
room_number TEXT UNIQUE NOT NULL,
owner_id INTEGER,
owner_name TEXT,
created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)
");
$db->exec("
CREATE TABLE IF NOT EXISTS users (
id INTEGER PRIMARY KEY AUTOINCREMENT,
name TEXT NOT NULL,
email TEXT UNIQUE NOT NULL,
password TEXT NOT NULL,
role TEXT NOT NULL DEFAULT 'member',
room_id INTEGER,
last_seen DATETIME,
created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)
");
$db->exec("
CREATE TABLE IF NOT EXISTS messages (
id INTEGER PRIMARY KEY AUTOINCREMENT,
room_id INTEGER NOT NULL,
user_id INTEGER NOT NULL,
sender_name TEXT NOT NULL,
message TEXT NOT NULL,
created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)
");
}

// ── helpers ──────────────────────────────────────────────────────────────────

function getUserById($db, $id) {
$s = $db->prepare("SELECT * FROM users WHERE id = ?");
$s->execute([$id]);
return $s->fetch();
}

function getUserByEmail($db, $email) {
$s = $db->prepare("SELECT * FROM users WHERE email = ?");
$s->execute([$email]);
return $s->fetch();
}

function getRoomById($db, $id) {
$s = $db->prepare("SELECT * FROM rooms WHERE id = ?");
$s->execute([$id]);
return $s->fetch();
}

function getRoomByNumber($db, $num) {
$s = $db->prepare("SELECT * FROM rooms WHERE room_number = ?");
$s->execute([$num]);
return $s->fetch();
}

function getAllRooms($db) {
return $db->query("SELECT * FROM rooms ORDER BY room_number ASC")->fetchAll();
}

function getMembersByRoom($db, $roomId) {
$s = $db->prepare("SELECT * FROM users WHERE room_id = ? ORDER BY name ASC");
$s->execute([$roomId]);
return $s->fetchAll();
}

function updateLastSeen($db, $userId) {
$s = $db->prepare("UPDATE users SET last_seen = CURRENT_TIMESTAMP WHERE id = ?");
$s->execute([$userId]);
}

/** User is online if last_seen is within 5 minutes */
function isOnline($lastSeen) {
if (!$lastSeen) return false;
return (time() - strtotime($lastSeen)) < 300;
}

function h($str) {
return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
