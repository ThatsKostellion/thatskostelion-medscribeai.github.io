<?php
// ─── Auth handler ─────────────────────────────────────────────────────────────
// Handles POST for: login, signup, logout
// Always redirects back after processing.

session_start();
require_once 'db.php';

$action = isset($_POST['action']) ? $_POST['action'] : '';
$db = getDB();

// ── LOGOUT ───────────────────────────────────────────────────────────────────
if ($action === 'logout') {
session_destroy();
header('Location: index.php?page=login');
exit;
}

// ── LOGIN ─────────────────────────────────────────────────────────────────────
if ($action === 'login') {
$email = trim(isset($_POST['email']) ? $_POST['email'] : '');
$password = isset($_POST['password']) ? $_POST['password'] : '';
$role = isset($_POST['role']) ? $_POST['role'] : 'member';
$roomId = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;

if (!$email || !$password) {
$_SESSION['auth_error'] = 'Please enter your email and password.';
header('Location: index.php?page=login');
exit;
}

$user = getUserByEmail($db, $email);

if (!$user || !password_verify($password, $user['password'])) {
$_SESSION['auth_error'] = 'Incorrect email or password.';
header('Location: index.php?page=login');
exit;
}

if ($user['role'] !== $role) {
$_SESSION['auth_error'] = 'This account is registered as a ' . $user['role'] . ', not ' . $role . '.';
header('Location: index.php?page=login');
exit;
}

// Members must confirm their room at login
if ($role === 'member' && (int)$user['room_id'] !== $roomId) {
$_SESSION['auth_error'] = 'Wrong room selected. Please choose the correct clinic room.';
header('Location: index.php?page=login');
exit;
}

$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['auth_error'] = null;
header('Location: index.php');
exit;
}

// ── SIGNUP ───────────────────────────────────────────────────────────────────
if ($action === 'signup') {
$name = trim(isset($_POST['name']) ? $_POST['name'] : '');
$email = trim(isset($_POST['email']) ? $_POST['email'] : '');
$password = isset($_POST['password']) ? $_POST['password'] : '';
$role = isset($_POST['role']) ? $_POST['role'] : 'member';

// Basic validation
if (!$name || !$email || !$password) {
$_SESSION['auth_error'] = 'Please fill in all fields.';
header('Location: index.php?page=signup');
exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
$_SESSION['auth_error'] = 'Invalid email address.';
header('Location: index.php?page=signup');
exit;
}
if (strlen($password) < 6) {
$_SESSION['auth_error'] = 'Password must be at least 6 characters.';
header('Location: index.php?page=signup');
exit;
}
if (getUserByEmail($db, $email)) {
$_SESSION['auth_error'] = 'An account with this email already exists.';
header('Location: index.php?page=signup');
exit;
}

$hash = password_hash($password, PASSWORD_DEFAULT);

// ── Owner signup ──────────────────────────────────────────────────────────
if ($role === 'owner') {
$roomNumber = strtoupper(trim(isset($_POST['room_number']) ? $_POST['room_number'] : ''));
if (!$roomNumber) {
$_SESSION['auth_error'] = 'Please enter a room number for your clinic.';
header('Location: index.php?page=signup');
exit;
}
if (preg_match('/[^A-Z0-9\-_]/', $roomNumber)) {
$_SESSION['auth_error'] = 'Room number may only contain letters, numbers, hyphens, and underscores.';
header('Location: index.php?page=signup');
exit;
}
if (getRoomByNumber($db, $roomNumber)) {
$_SESSION['auth_error'] = 'Room number "' . h($roomNumber) . '" is already taken. Choose another.';
header('Location: index.php?page=signup');
exit;
}

// Create user first (room_id set after room creation)
$s = $db->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'owner')");
$s->execute([$name, $email, $hash]);
$userId = (int)$db->lastInsertId();

// Create room
$s = $db->prepare("INSERT INTO rooms (room_number, owner_id, owner_name) VALUES (?, ?, ?)");
$s->execute([$roomNumber, $userId, $name]);
$roomId = (int)$db->lastInsertId();

// Assign room back to user
$s = $db->prepare("UPDATE users SET room_id = ? WHERE id = ?");
$s->execute([$roomId, $userId]);

$_SESSION['user_id'] = $userId;
$_SESSION['auth_error'] = null;
header('Location: index.php');
exit;
}

// ── Member signup ─────────────────────────────────────────────────────────
$roomId = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;
if (!$roomId) {
$_SESSION['auth_error'] = 'Please select a clinic room to join.';
header('Location: index.php?page=signup');
exit;
}
$room = getRoomById($db, $roomId);
if (!$room) {
$_SESSION['auth_error'] = 'Selected room does not exist.';
header('Location: index.php?page=signup');
exit;
}

$s = $db->prepare("INSERT INTO users (name, email, password, role, room_id) VALUES (?, ?, ?, 'member', ?)");
$s->execute([$name, $email, $hash, $roomId]);
$_SESSION['user_id'] = (int)$db->lastInsertId();
$_SESSION['auth_error'] = null;
header('Location: index.php');
exit;
}

// Fallback
header('Location: index.php');
exit;
