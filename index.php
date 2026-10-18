<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sohail's Blog</title>
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
                <img style="border-radius: 50%; margin: 0; filter: grayscale(100%);" src="/ui/sohail-sarwar.png"
                    alt="Sohail Sarwar" width="60" height="60">
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
            $posts = glob("posts/post_*.md");

            if (empty($posts)) {
                echo "<p>No posts found yet. Send an email to get started!</p>";
            } else {
                array_multisort(array_map('filemtime', $posts), SORT_DESC, $posts);

                $posts_per_page = 10;
                $total_posts = count($posts);
                $total_pages = ceil($total_posts / $posts_per_page);

                $current_page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
                if ($current_page < 1)
                    $current_page = 1;
                if ($current_page > $total_pages && $total_pages > 0)
                    $current_page = $total_pages;

                $offset = ($current_page - 1) * $posts_per_page;
                $paged_posts = array_slice($posts, $offset, $posts_per_page);

                foreach ($paged_posts as $post_file) {
                    $file_lines = file($post_file);

                    // 🔒 SAFE EXTRACTION: Parses explicit line prefixes
                    $title = str_replace("TITLE: ", "", trim($file_lines[0]));
                    $slug = str_replace("SLUG: ", "", trim($file_lines[1]));
                    $date = str_replace("DATE: ", "", trim($file_lines[2]));

                    preg_match('/post_(.*?)\.md/', $post_file, $matches);
                    $post_hash = $matches[1];
                    $clean_date = date("D, j M Y", strtotime($date));

                    $clean_url = trim($slug) . "-" . $post_hash;

                    echo "<article>";
                    echo "<h2><a href='" . $clean_url . "'>" . htmlspecialchars($title) . "</a></h2>";
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
                        $next_page = $current_page + 1;
                        echo "<a href='?page=" . $next_page . "'>Next →</a>";
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