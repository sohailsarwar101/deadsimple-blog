<?php
// Get the requested URL path
$path = ltrim($_SERVER["REQUEST_URI"], '/');

// 1. If it's a real file or directory, let PHP serve it normally
if (file_exists(__DIR__ . '/' . $path) && !is_dir(__DIR__ . '/' . $path)) {
    return false;
}

// 2. Apply your Regex rule: ^([a-z0-9-]+)-([a-f0-9]{32})$
// Example matches: "my-first-post-a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6"
if (preg_match('/^([a-z0-9-]+)-([a-f0-9]{32})$/', $path, $matches)) {
    // Inject the hash into the $_GET superglobal just like QSA
    $_GET['id'] = $matches[2];

    // Load your post file
    include 'post.php';
    exit;
}

// 3. Fallback for your main index.php or 404 pages
if ($path === '' || $path === 'index.php') {
    include 'index.php';
    exit;
}

// Return false to let static assets (CSS/JS) pass through
return false;
