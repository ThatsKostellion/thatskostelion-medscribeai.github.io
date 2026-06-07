<?php
// ─── App configuration template ───────────────────────────────────────────────
// Copy this file to "config.php" and fill in your real values.
// config.php is intentionally excluded from this repo (see .gitignore) because
// it holds the live DeepSeek API key — never commit that file.

// DeepSeek API key used by app.php / api.php for transcript polishing and
// medical-record generation (see https://platform.deepseek.com for keys).
define('DEEPSEEK_API_KEY', 'sk-REPLACE_WITH_YOUR_OWN_DEEPSEEK_KEY');

// Optional: override the default DeepSeek API endpoint / model if needed.
define('DEEPSEEK_API_URL', 'https://api.deepseek.com/chat/completions');
define('DEEPSEEK_MODEL', 'deepseek-chat');
