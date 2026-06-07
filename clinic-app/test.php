<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>PHP OK — version: " . PHP_VERSION . "</h2>";

// Test session
session_start();
echo "<b>Session:</b> OK<br>";

// Test mkdir
$dir = __DIR__ . '/doctors';
if (!is_dir($dir)) {
$ok = @mkdir($dir, 0755, true);
echo "<b>mkdir doctors:</b> " . ($ok ? 'OK' : 'FAILED — ' . json_encode(error_get_last())) . "<br>";
} else {
echo "<b>doctors dir:</b> already exists ✅<br>";
}

// Test includes
echo "<hr>Testing includes...<br>";
try {
require_once __DIR__ . '/config.php';
echo "config.php: ✅<br>";
} catch (Throwable $e) {
echo "config.php ERROR: " . $e->getMessage() . "<br>";
}

try {
require_once __DIR__ . '/auth.php';
echo "auth.php: ✅<br>";
} catch (Throwable $e) {
echo "auth.php ERROR: " . $e->getMessage() . "<br>";
}

echo "<hr><b>All done!</b>";
