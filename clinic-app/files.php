<?php
require_once __DIR__ . '/auth.php';
header('Content-Type: application/json');

if (!is_logged_in()) {
echo json_encode(['files' => []]);
exit;
}

$username = $_SESSION['doctor_username'];
$base = get_doctor_dir($username) . '/documents';

$files = [];
foreach (['reports', 'transcripts'] as $sub) {
$dir = $base . '/' . $sub;
if (!is_dir($dir)) continue;
foreach (glob($dir . '/*.txt') as $path) {
$files[] = [
'name' => basename($path),
'date' => date('Y-m-d H:i', filemtime($path)),
];
}
}

usort($files, fn($a, $b) => strcmp($b['date'], $a['date']));
echo json_encode(['files' => $files]);
