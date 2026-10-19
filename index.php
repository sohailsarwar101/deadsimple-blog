<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://yourdomain.com">
    <meta name="author" content="Sohail Sarwar">

    <title>deadSimple Blog</title>
    <meta name="description" content="deadSimple Blog ...">

    <!-- Open Graph / Facebook (For social sharing optimization) -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="deadSimple Blog">
    <meta property="og:description" content="deadSimple Blog ...">
    <meta property="og:url" content="https://yourdomain.com">
    <meta property="og:image" content="https://yourdomain.com">

    <!-- Twitter Cards -->
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="deadSimple Blog">
    <meta name="twitter:description" content="deadSimple Blog ...">
    <meta name="twitter:image" content="https://yourdomain.com">

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

        header {}

        a {
            color: #777;
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
            color: #0066cc;
        }

        article {
            margin-bottom: 18px;
            border-bottom: 1px solid #eee;
            padding-bottom: 20px;
        }

        h2 {
            margin-bottom: 0;
            font-size: 1.1rem;
            font-weight: 500;
        }

        h2 a {
            color: #111;
            text-decoration: none;
        }

        h2 a:hover {
            color: #0066cc;
        }

        small {
            color: #777;
            display: block;
            font-size: 12px;
        }

        /* Pagination Styling Layout */
        .pagination {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
            margin-bottom: 50px;
            font-size: 13px;
        }

        .pagination a {
            color: #0066cc;
            text-decoration: none;
            font-weight: 500;
        }

        .pagination a:hover {
            text-decoration: underline;
        }

        .pagination .disabled {
            color: #ccc;
            pointer-events: none;
        }
    </style>
</head>

<body>
    <div
        style="max-width: 480px; background: #fff; border-left: 1px solid #eee; border-right: 1px solid #eee; min-height: 100vh; padding: 0 14px; box-sizing: border-box; display: flex; flex-direction: column;">
        <header style="padding-top: 72px;">
            <a href="/">
                <img style="border-radius: 50%; margin: 0;" src="/ui/avatar.png" alt="author" width="60" height="60">
            </a>
            <div style="margin-top: -4px;">
                <h1 style="margin: 0;"><a href="/"
                        style="color:#111; text-decoration:none; font-weight: 500; font-size: 1.1rem;">Sohail Sarwar</a>
                </h1>
                <p style="margin-top: -5px; font-size: 13px; color: #777;">Software Design & Engineering Professional.
                </p>
            </div>
        </header>

        <main style="border-top: 1px solid #eee; padding-top: 6px; margin-top: 20px; flex: 1;">
            <?php
            $posts = glob(__DIR__ . "/posts/*.md");

            if (empty($posts)) {
                echo "<p>No posts found yet. Send an email to get started!</p>";
            } else {
                // Newest first (by file modified time)
                usort($posts, fn($a, $b) => filemtime($b) <=> filemtime($a));

                $posts_per_page = 10;
                $total_posts = count($posts);
                $total_pages = (int) ceil($total_posts / $posts_per_page);

                $current_page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
                if ($current_page < 1)
                    $current_page = 1;
                if ($current_page > $total_pages)
                    $current_page = $total_pages;

                $offset = ($current_page - 1) * $posts_per_page;
                $paged_posts = array_slice($posts, $offset, $posts_per_page);

                foreach ($paged_posts as $post_file) {
                    // File name IS the slug: posts/post-one.md -> "post-one"
                    $slug = basename($post_file, '.md');

                    // Title = first non-empty line of the file, minus markdown "#" marks
                    $title = '';
                    $fh = fopen($post_file, 'r');
                    if ($fh) {
                        while (($line = fgets($fh)) !== false) {
                            $line = trim(preg_replace('/^\xEF\xBB\xBF/', '', $line)); // strip BOM
                            if ($line !== '') {
                                $title = trim(ltrim($line, "# \t"));
                                break;
                            }
                        }
                        fclose($fh);
                    }
                    // Fallback: prettify the slug ("post-one" -> "Post One")
                    if ($title === '') {
                        $title = ucwords(str_replace(['-', '_'], ' ', $slug));
                    }

                    $clean_date = date("D, j M Y", filemtime($post_file));
                    $clean_url = "/" . rawurlencode($slug);

                    echo "<article>";
                    echo "<h2><a href='" . htmlspecialchars($clean_url, ENT_QUOTES) . "'>" . htmlspecialchars($title) . "</a></h2>";
                    echo "<small>Published on: " . htmlspecialchars($clean_date) . "</small>";
                    echo "</article>";
                }

                if ($total_pages > 1) {
                    echo "<div class='pagination'>";
                    if ($current_page > 1) {
                        $prev_page = $current_page - 1;
                        $prev_url = ($prev_page === 1) ? "/" : "?page=" . $prev_page;
                        echo "<a href='" . $prev_url . "'>← Previous</a>";
                    } else {
                        echo "<span class='disabled'>← Previous</span>";
                    }

                    if ($current_page < $total_pages) {
                        echo "<a href='?page=" . ($current_page + 1) . "'>Next →</a>";
                    } else {
                        echo "<span class='disabled'>Next →</span>";
                    }
                    echo "</div>";
                }
            }
            ?>
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