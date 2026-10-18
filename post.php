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

$blog_email = 'sohail.homeallure+blog@gmail.com';
$secret_prefix = '[BLOG]';

$mail_subject = rawurlencode("Re: " . $secret_prefix . " " . trim($title));
$mail_body = rawurlencode("\n\n--- Write your comment ABOVE this line ---\n(Please keep the subject line intact so your comment nests correctly.)");
$mailto_url = "mailto:" . $blog_email . "?subject=" . $mail_subject . "&body=" . $mail_body;

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
    <title><?php echo htmlspecialchars($title); ?> - My Email Blog</title>
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

        .email-link-container {
            margin-top: 30px;
            margin-bottom: 30px;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }

        .email-btn {
            display: inline-block;
            background: #0066cc;
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 4px;
            font-weight: bold;
        }

        .email-btn:hover {
            background: #004c99;
        }

        .comment-section {
            margin-top: 40px;
            background: #f9f9f9;
            padding: 25px;
            border-left: 4px solid #0066cc;
            border-radius: 4px;
        }

        .comment-section h3 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #333;
        }

        .comment {
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }

        .comment:last-child {
            border-bottom: none;
            padding-bottom: 0;
            margin-bottom: 0;
        }

        .comment-meta {
            font-weight: bold;
            color: #555;
            font-size: 0.85rem;
            margin-bottom: 5px;
        }
    </style>
</head>

<body>

    <div
        style="max-width: 480px; background: #fff; border-left: 1px solid #eee; border-right: 1px solid #eee; min-height: 100vh; padding: 0 14px; box-sizing: border-box; display: flex; flex-direction: column;">
        <header style="padding-top: 32px; display: flex; flex-direction: column;">
            <a href="/">
                <img style="border-radius: 50%; margin: 0; filter: grayscale(100%);" src="/ui/sohail-sarwar.png"
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

            <!--
            <div class="email-link-container">
                <a href="<?php // echo $mailto_url; ?>" class="email-btn">Comment via Email</a>
            </div>
            -->

            <?php
            $comment_file = "posts/comments_" . $post_hash . ".md";
            if (file_exists($comment_file) && !empty(trim(file_get_contents($comment_file)))) {
                echo "<div class='comment-section'>";
                echo "<h3>Discussion Room</h3>";

                $all_comments = str_replace("\r\n", "\n", file_get_contents($comment_file));
                $comments_array = preg_split('/^={10,}\s*$/m', $all_comments);

                foreach ($comments_array as $individual_comment) {
                    $individual_comment = trim($individual_comment);
                    if ($individual_comment === '')
                        continue;

                    $lines = explode("\n", $individual_comment);
                    $raw_from = isset($lines[0]) ? str_replace("FROM: ", "", trim($lines[0])) : 'Anonymous Reader';
                    $c_raw_date = isset($lines[1]) ? str_replace("DATE: ", "", trim($lines[1])) : date('Y-m-d');

                    $display_name = "Anonymous Reader";
                    if (preg_match('/^(.*?)\s*<(.*?)>/', $raw_from, $matches)) {
                        $name_part = trim($matches[1], '"\' ');
                        if (!empty($name_part)) {
                            $display_name = $name_part;
                        } else {
                            list($user, $domain) = explode('@', trim($matches[2]), 2);
                            $display_name = substr($user, 0, 3) . '***@' . $domain;
                        }
                    } else {
                        if (strpos($raw_from, '@') !== false) {
                            list($user, $domain) = explode('@', $raw_from, 2);
                            $display_name = substr($user, 0, 3) . '***@' . $domain;
                        } else {
                            $display_name = trim($raw_from);
                        }
                    }

                    $c_clean_date = date("D, j M Y", strtotime($c_raw_date));
                    $c_body_lines = array_slice($lines, 2);
                    $c_body = implode("\n", $c_body_lines);
                    $c_body = preg_replace('/^BODY:\s*/i', '', trim($c_body));

                    // Apply the same line unwrap filter to the comment layout bodies
                    $c_body = preg_replace('/(?<!\n)\n(?!\n)/', ' ', $c_body);

                    if (!empty(trim($c_body))) {
                        echo "<div class='comment'>";
                        echo "<div class='comment-meta'>" . htmlspecialchars($display_name) . " • <span style='font-weight:normal; color:#888;'>" . htmlspecialchars($c_clean_date) . "</span></div>";
                        echo "<p style='margin: 0; white-space: pre-wrap;'>" . htmlspecialchars(trim($c_body)) . "</p>";
                        echo "</div>";
                    }
                }
                echo "</div>";
            }
            ?>
        </main>

        <footer
            style="padding-bottom: 24px; margin-top: auto; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
            <div style="">
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