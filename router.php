<?php
// Router for PHP's built-in server (localhost only; Apache uses .htaccess instead)
// Run with: php -S localhost:8000 router.php

// Get only the path, without the query string (so /?page=2 still works)
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$path = trim(rawurldecode($uri), '/');

// 1. Real file or folder (css, images, index.php, /ui/avatar.png): serve it normally
if ($path !== '' && file_exists(__DIR__ . '/' . $path)) {
    return false;
}

// 2. Homepage
if ($path === '' || $path === 'index.php') {
    require __DIR__ . '/index.php';
    return true;
}

// 3. Post slug: /post-one -> posts/post-one.md
if (preg_match('/^[A-Za-z0-9_-]+$/', $path)) {
    $_GET['slug'] = $path; // same effect as QSA in the .htaccess rule
    require __DIR__ . '/post.php';
    return true;
}

// 4. Anything else
http_response_code(404);
echo "Not found.";
return true;