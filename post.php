<?php
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

$raw_body = ltrim(implode("", array_slice($file_lines, $title_index + 1)), "\r\n");

// Same date source as the index page
$clean_date = date("D, j M Y", filemtime($post_file));

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

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?> - deadSimple Blog</title>
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