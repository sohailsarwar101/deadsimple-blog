<?php
if (!isset($_GET['id']) || !preg_match('/^[a-f0-9]{32}$/', $_GET['id'])) {
    header("Location: index.php");
    exit;
}

$post_hash = $_GET['id'];
$post_file = "posts/post_" . $post_hash . ".md";

if (!file_exists($post_file)) {
    die("Post not found.");
}

$content = file_get_contents($post_file);
$file_lines = file($post_file);
$title = str_replace("TITLE: ", "", trim($file_lines[0]));
$slug = str_replace("SLUG: ", "", trim($file_lines[1]));
$date = str_replace("DATE: ", "", trim($file_lines[2]));
$clean_date = date("D, j M Y", strtotime($date));

// 🔧 FIX: Start slice at index 5 to completely skip the "CONTENT:" metadata line label
// Find the CONTENT: line dynamically instead of hardcoding an index
$content_index = 3;
foreach ($file_lines as $i => $l) {
    if (stripos(trim($l), 'CONTENT:') === 0) {
        $content_index = $i;
        break;
    }
}
$raw_body = ltrim(implode("", array_slice($file_lines, $content_index + 1)), "\r\n");

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

    // Bold, then italics
    $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/', '<em>$1</em>', $text);

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
        if (preg_match('/^(#{1,3})\s+(.*)$/', $line, $m)) {
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
        } elseif (preg_match('/^!\[([^\]]*)\]\(([^)\s]+)\)$/', $line)) {
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
            font-size: 1.1rem;
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
        <header style="padding-top: 32px; display: flex; flex-direction: column;">
            <a href="/">
                <img style="border-radius: 50%; margin: 0; filter: grayscale(100%);" src="/ui/avatar.png"
                    alt="Sohail Sarwar" width="48" height="48">
            </a>
            <a href="/" style="margin-top: 24px;" class="back-link">← Back to Index</a>
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