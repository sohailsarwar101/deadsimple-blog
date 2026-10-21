<?php
date_default_timezone_set('Asia/Dubai');
// Slug comes from the URL: /post-one -> posts/post-one.md
$slug = $_GET['slug'] ?? '';

// Only allow safe characters (blocks ../ and other path tricks)
if (!preg_match('/^[A-Za-z0-9_-]+$/', $slug)) {
    header("Location: /");
    exit;
}

$post_file = __DIR__ . "/posts/" . $slug . ".md";

if (!is_file($post_file)) {
    http_response_code(404);
    die("Post not found.");
}

// ---- Likes ----
const MAX_LIKES_PER_VISITOR = 10;

$dataDir = __DIR__ . '/data';
$likesFile = $dataDir . '/likes.json';
if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0755, true);
    @file_put_contents($dataDir . '/.htaccess', "Require all denied\n"); // keep data/ private (Apache)
}

function likes_read($file)
{
    $d = json_decode((string) @file_get_contents($file), true);
    return is_array($d) ? $d : [];
}

// Anonymous visitor id for this post: HMAC of IP + slug (raw IP is never stored)
function visitor_key($slug)
{
    $secretFile = __DIR__ . '/data/secret.key';
    if (!is_file($secretFile)) {
        @file_put_contents($secretFile, bin2hex(random_bytes(32)));
    }
    $secret = trim((string) @file_get_contents($secretFile));
    return hash_hmac('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . $slug, $secret);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['like'])) {
    $lock = fopen($dataDir . '/likes.lock', 'c');
    if ($lock && flock($lock, LOCK_EX)) {
        $tally = likes_read($likesFile);
        $key = visitor_key($slug);

        if (($tally[$key] ?? 0) < MAX_LIKES_PER_VISITOR) {
            $lines = file($post_file);

            // Title line = first non-empty line
            $ti = -1;
            foreach ($lines as $i => $l) {
                if (trim(preg_replace('/^\xEF\xBB\xBF/', '', $l)) !== '') {
                    $ti = $i;
                    break;
                }
            }

            if ($ti >= 0) {
                // Find an existing Likes line, or where to insert one (after title / Date line)
                $likesAt = null;
                $insertAt = $ti + 1;
                for ($j = $ti + 1; $j < count($lines); $j++) {
                    $t = trim($lines[$j]);
                    if ($t === '')
                        continue;
                    if (!preg_match('/^(date|likes):/i', $t, $m))
                        break;
                    if (strtolower($m[1]) === 'likes') {
                        $likesAt = $j;
                        break;
                    }
                    $insertAt = $j + 1;
                }

                if ($likesAt !== null) {
                    $cur = (int) preg_replace('/\D/', '', $lines[$likesAt]);
                    $lines[$likesAt] = 'Likes: ' . ($cur + 1) . "\n";
                } else {
                    $lines[$insertAt - 1] = rtrim($lines[$insertAt - 1], "\r\n") . "\n";
                    array_splice($lines, $insertAt, 0, "Likes: 1\n");
                }

                $mtime = filemtime($post_file);
                if (@file_put_contents($post_file, implode('', $lines)) !== false) {
                    touch($post_file, $mtime); // keep the original modified time
                    $tally[$key] = ($tally[$key] ?? 0) + 1;
                    file_put_contents($likesFile, json_encode($tally));
                }
            }
        }
        flock($lock, LOCK_UN);
    }
    header('Location: /' . $slug . '#likes', true, 303);
    exit;
}

$file_lines = file($post_file);

// Title = first non-empty line (minus markdown "#" marks); body = everything after it
$title = '';
$title_index = -1;
foreach ($file_lines as $i => $l) {
    $l = trim(preg_replace('/^\xEF\xBB\xBF/', '', $l)); // strip BOM
    if ($l !== '') {
        $title = trim(ltrim($l, "# \t"));
        $title_index = $i;
        break;
    }
}
if ($title === '') {
    $title = ucwords(str_replace(['-', '_'], ' ', $slug));
}

$raw_body = implode("", array_slice($file_lines, $title_index + 1));

// Optional "Date:" and "Likes:" lines right after the title (any order)
$ts = filemtime($post_file);
$likes = 0;
while (preg_match('/^\s*(date|likes):[ \t]*(.*?)[ \t]*(?:\R|$)/i', $raw_body, $m)) {
    if (strtolower($m[1]) === 'date') {
        if ($d = strtotime($m[2]))
            $ts = $d;
    } else {
        $likes = max(0, (int) $m[2]);
    }
    $raw_body = substr($raw_body, strlen($m[0]));
}
$raw_body = ltrim($raw_body, "\r\n");
$clean_date = date("D, j M Y", $ts);
$mine = likes_read($likesFile)[visitor_key($slug)] ?? 0; // likes this visitor has used

// 📝 ULTRA-LIGHTWEIGHT NATIVE MARKDOWN PARSER
function parse_inline($text)
{
    // Images: ![alt](url), processed first so the link rule doesn't grab them
    $text = preg_replace_callback('/!\[([^\]]*)\]\(([^)\s]+)\)/', function ($m) {
        if (!preg_match('#^(https?://|/)#i', $m[2])) {
            return $m[0]; // block javascript:, data:, etc.
        }
        return '<img src="' . $m[2] . '" alt="' . $m[1] . '" loading="lazy">';
    }, $text);

    // Bold+italic, bold, then italics (both * and _ styles)
    $text = preg_replace('/\*\*\*(.+?)\*\*\*/', '<strong><em>$1</em></strong>', $text);
    $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/(?<!\w)__(.+?)__(?!\w)/', '<strong>$1</strong>', $text);
    $text = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/', '<em>$1</em>', $text);
    // Underscore italics: the \w guards keep snake_case words and URLs untouched
    $text = preg_replace('/(?<![\w_])_(?!_)(.+?)(?<!_)_(?![\w_])/', '<em>$1</em>', $text);

    // Links (only allow safe schemes)
    $text = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', function ($m) {
        if (!preg_match('#^(https?://|mailto:|/)#i', $m[2])) {
            return $m[0];
        }
        return '<a href="' . $m[2] . '" target="_blank" rel="noopener noreferrer">' . $m[1] . '</a>';
    }, $text);

    return $text;
}

function parse_markdown($text)
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

    $out = [];
    $para = [];
    $listType = ''; // '', 'ul' or 'ol'

    $flushPara = function () use (&$para, &$out) {
        if ($para) {
            $out[] = '<p>' . implode(' ', array_map('parse_inline', $para)) . '</p>';
            $para = [];
        }
    };
    $closeList = function () use (&$listType, &$out) {
        if ($listType) {
            $out[] = "</$listType>";
            $listType = '';
        }
    };
    $openList = function ($type) use (&$listType, &$out, $closeList) {
        if ($listType !== $type) {
            $closeList();              // switching ul <-> ol
            $out[] = "<$type>";
            $listType = $type;
        }
    };

    foreach (explode("\n", $text) as $line) {
        $line = rtrim($line);

        if ($line === '') {
            $flushPara();
            $closeList();
            continue;
        }

        // Headers
        if (preg_match('/^(#{1,6})\s+(.*)$/', $line, $m)) {
            $flushPara();
            $closeList();
            $n = strlen($m[1]);
            $out[] = "<h$n>" . parse_inline($m[2]) . "</h$n>";
        }
        // Blockquote
        elseif (preg_match('/^&gt;\s?(.*)$/', $line, $m)) {
            $flushPara();
            $closeList();
            $out[] = '<blockquote>' . parse_inline($m[1]) . '</blockquote>';
        }
        // Unordered list (- or *)
        elseif (preg_match('/^[-*]\s+(.*)$/', $line, $m)) {
            $flushPara();
            $openList('ul');
            $out[] = '<li>' . parse_inline($m[1]) . '</li>';
        }
        // Ordered list (1. or 1))
        elseif (preg_match('/^\d+[.)]\s+(.*)$/', $line, $m)) {
            $flushPara();
            $openList('ol');
            $out[] = '<li>' . parse_inline($m[1]) . '</li>';
        }
        // Standalone image
        elseif (preg_match('/^!\[([^\]]*)\]\(([^)\s]+)\)$/', $line)) {
            $flushPara();
            $closeList();
            $out[] = '<figure>' . parse_inline($line) . '</figure>';
        }
        // Horizontal rule (---, ***, ___)
        elseif (preg_match('/^(-{3,}|\*{3,}|_{3,})$/', $line)) {
            $flushPara();
            $closeList();
            $out[] = '<hr>';
        }
        // Regular text
        else {
            $closeList();
            $para[] = $line;
        }
    }

    $flushPara();
    $closeList();

    return implode("\n", $out);
}

function get_meta_description($raw_body, $limit = 150)
{
    $html = parse_markdown($raw_body);
    $plain = strip_tags($html);
    $clean = preg_replace('/\s+/', ' ', trim($plain));

    if (mb_strlen($clean) <= $limit) {
        return $clean;
    }

    $trimmed = mb_substr($clean, 0, $limit);
    return mb_substr($trimmed, 0, mb_strrpos($trimmed, ' ')) . '...';
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="canonical" href="https://yourdomain.com/<?php echo htmlspecialchars($slug); ?>">
    <meta name="author" content="Sohail Sarwar">

    <title><?php echo htmlspecialchars($title); ?> - deadSimple Blog</title>

    <!-- Open Graph / Facebook (Notice type changes to 'article') -->
    <meta property="og:type" content="article">
    <meta property="og:title" content="<?php echo htmlspecialchars($title); ?>">
    <meta property="og:description"
        content="<?php echo htmlspecialchars(get_meta_description($raw_body), ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:url" content="https://yourdomain.com/<?php echo htmlspecialchars($slug); ?>">
    <meta property="article:published_time" content="<?php echo date('c', $ts); ?>">
    <meta property="article:author" content="Sohail Sarwar">

    <!-- Twitter Cards -->
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($title); ?>">
    <meta name="twitter:description"
        content="<?php echo htmlspecialchars(get_meta_description($raw_body), ENT_QUOTES, 'UTF-8'); ?>">

    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 850px;
            margin: 0 auto;
            padding: 0 16px;
            color: #333;
            line-height: 1.6;
            background: #fdfdfd;
        }

        a {
            color: #777;
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
            color: #0066cc;
        }

        header {
            margin-bottom: 0;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #0066cc;
            text-decoration: none;
            font-size: 0.9rem;
        }

        h1 {
            margin-bottom: 5px;
            color: #111;
            font-size: 1.15rem;
            font-weight: 500;
        }

        h2 {
            margin-bottom: 5px;
            color: #111;
            font-size: 1.05rem;
            font-weight: 500;
        }

        h3 {
            margin-bottom: 5px;
            color: #111;
            font-size: 0.95rem;
            font-weight: 500;
        }

        h4,
        h5,
        h6 {
            color: #111;
            font-size: 15px;
            font-weight: 500;
        }

        small {
            color: #777;
            display: block;
            font-size: 12px;
        }

        /* Markdown Specific Styling elements */
        .post-body {
            font-size: 15px;
            margin-bottom: 40px;
            margin-top: 24px;
            text-align: justify;
        }

        .post-body strong {
            font-weight: 600;
        }

        .post-body hr {
            border: 0;
            border-top: 1px solid #eee;
            margin: 28px 0;
        }

        .post-body blockquote {
            border-left: 4px solid #ddd;
            padding-left: 15px;
            margin: 20px 0;
            color: #666;
            font-style: italic;
        }

        .post-body ul,
        ol {
            padding-left: 20px;
            margin: 15px 0;
        }

        .post-body a {
            color: #0066cc;
            text-decoration: underline;
        }

        .post-body figure,
        img {
            max-width: 100%;
            height: auto;
            display: block;
            margin: 20px 0;
        }

        .like-box {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0 0 40px;
            font-size: 13px;
            color: #777;
        }

        .like-box button {
            border: 1px solid #ddd;
            background: #fff;
            border-radius: 999px;
            padding: 6px 14px;
            font: inherit;
            color: #333;
            cursor: pointer;
        }

        .like-box button:hover:not(:disabled) {
            border-color: #0066cc;
            color: #0066cc;
        }

        .like-box button:disabled {
            opacity: .5;
            cursor: default;
        }
    </style>
</head>

<body>

    <div
        style="max-width: 480px; background: #fff; border-left: 1px solid #eee; border-right: 1px solid #eee; min-height: 100vh; padding: 0 14px; box-sizing: border-box; display: flex; flex-direction: column;">
        <header style="padding-top: 32px; display: flex; flex-direction: column; align-items: flex-start;">
            <a href="/">
                <img style="border-radius: 50%; margin: 0;" src="/ui/avatar.png" alt="author" width="48" height="48">
            </a>
            <a href="/" style="margin-top: 24px; display: inline-block;" class="back-link">← Back to Index</a>
        </header>

        <main>
            <article>
                <h1><?php echo htmlspecialchars($title); ?></h1>
                <small>Published on: <?php echo htmlspecialchars($clean_date); ?></small>
                <!-- ⚡ Render parsed Markdown content -->
                <div class="post-body"><?php echo parse_markdown($raw_body); ?></div>

                <form method="post" action="/<?php echo htmlspecialchars(rawurlencode($slug)); ?>" id="likes"
                    class="like-box">
                    <button type="submit" name="like" value="1" <?php echo $mine >= MAX_LIKES_PER_VISITOR ? 'disabled' : ''; ?>>♥ Like</button>
                    <span><?php echo number_format($likes); ?></span>
                </form>
            </article>
        </main>

        <footer
            style="padding-bottom: 24px; margin-top: auto; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
            <div>
                <small style="display: flex; gap: 14px;">
                    <a href="https://x.com/SohailSarwar101" target="_blank">X</a>
                    <a href="https://www.linkedin.com/in/sohailsarwar101/" target="_blank">LinkedIn</a>
                    <a href="mailto:mr.sohailsarwar@gmail.com" target="_blank">Email</a>
                </small>
            </div>
            <small>© <?php echo date('Y'); ?></small>
        </footer>
    </div>

</body>

</html>