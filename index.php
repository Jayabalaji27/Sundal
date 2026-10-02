<?php
// Subdirectory entry point for shared hosting at /sundal/
// KEY INSIGHT: DO NOT strip REQUEST_URI - Symfony's Request class auto-detects
// /sundal as the base URL from SCRIPT_NAME (/sundal/index.php) and strips it
// when computing pathInfo, so routes still match /login /dashboard etc.
//
// This means url('/') naturally returns https://codecartz.com/sundal/ ✓
// and route('login') returns https://codecartz.com/sundal/login ✓

// Only serve real static files from public/ directly
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$stripped = preg_replace('#^/sundal#', '', $uri) ?: '/';
if ($stripped !== '/' && file_exists(__DIR__ . '/public' . $stripped)) {
    // For static assets (CSS/JS/images) - adjust URI and serve via public/index.php
    // The file will be returned directly since it exists
    $_SERVER['REQUEST_URI'] = $stripped . (($_SERVER['QUERY_STRING'] ?? '') ? '?' . $_SERVER['QUERY_STRING'] : '');
    require_once __DIR__ . '/public/index.php';
    exit;
}

// Dynamic request - keep full REQUEST_URI (/sundal/...) so Symfony computes
// baseUrl = /sundal and pathInfo = /login, /dashboard, etc.
require_once __DIR__ . '/public/index.php';



