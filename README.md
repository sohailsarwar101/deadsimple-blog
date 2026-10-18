# deadSimple Blog

A tiny flat-file blog written in plain PHP. No database, no framework, no build step. Write a Markdown file, drop it in `posts/`, and it's live.

## How it works

1. You add a Markdown file to the `posts/` folder, for example `posts/post-one.md`.
2. The **file name is the slug**, so that post lives at `domain.com/post-one`.
3. The **first line of the file is the title** (for example `# My First Post`). Everything after it is the post body.
4. The **date is the file's last-modified time**. Editing a post moves it to the top of the index and updates its date.
5. The homepage lists all posts, newest first, 10 per page, with Previous / Next links.
6. When someone opens `/post-one`, the web server rewrites the URL to `post.php?slug=post-one`. `post.php` reads `posts/post-one.md`, converts the Markdown to HTML with a small built-in parser, and renders the page.

```
Request: /post-one
    -> .htaccess (Apache) or router.php (localhost)
    -> post.php?slug=post-one
    -> reads posts/post-one.md
    -> parses Markdown -> HTML page
```

## Project structure

```
deadSimple-blog/
├── index.php        Homepage: profile header, post list, pagination
├── post.php         Single post page: loads a .md file and renders it
├── router.php       Router for PHP's built-in server (localhost only)
├── .htaccess        Pretty-URL rewrite rule for Apache (live server)
├── posts/           Your Markdown posts, one file per post
│   └── post-one.md
└── ui/
    └── avatar.png   Profile picture shown in the header
```

### File by file

**`index.php`**
The homepage. It finds every `posts/*.md` file, sorts them by modified time (newest first), and shows 10 per page. For each file it reads the title from the first non-empty line (leading `#` marks are removed), links to `/<slug>`, and shows the modified date. If a file has no usable title, it falls back to a prettified slug (`post-one` becomes "Post One"). Page numbers come from `?page=2`, `?page=3`, and so on.

**`post.php`**
Renders one post. It reads the slug from `$_GET['slug']`, allows only letters, numbers, `-` and `_` (which blocks `../` path tricks), and loads `posts/<slug>.md`. A missing post returns a real 404. It contains the Markdown parser (see below) and the page layout.

**`router.php`**
Only used on localhost with PHP's built-in server, which doesn't read `.htaccess`. It does the same job as the Apache rule: real files and folders are served as they are, `/` loads `index.php`, any valid slug loads `post.php`, and anything else returns 404.

**`.htaccess`**
Used on Apache. It rewrites `domain.com/post-one` to `post.php?slug=post-one`, skipping real files and folders:

```apache
RewriteEngine On

# Prevent rewriting if the request points to a real existing file or folder
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d

# domain.com/post-one -> post.php?slug=post-one
RewriteRule ^([a-zA-Z0-9_-]+)/?$ post.php?slug=$1 [L,QSA]
```

**`posts/`**
Your content. One `.md` file per post. Don't put other `.md` files here (like a `README.md`), because they would show up as posts.

**`ui/avatar.png`**
The round, grayscale profile picture in the header of both pages.

## Run it on localhost

You need PHP 7.4 or newer. From the project folder, run:

```bash
php -S localhost:8000 router.php
```

Then open [http://localhost:8000](http://localhost:8000) and visit a post at `http://localhost:8000/post-one`. Stop the server with `Ctrl + C`.

## Deploy on Apache

1. Upload all the files to your web root.
2. Make sure `mod_rewrite` is enabled and the site has `AllowOverride All`, so `.htaccess` is read.
3. Make sure the `posts/` folder is readable by the web server.

`router.php` isn't used on Apache. It's harmless to leave in place.

## Writing a post

Create `posts/my-post.md` (lowercase letters, numbers, `-` and `_` only; no spaces):

```markdown
# Your Post Title Here

First paragraph. Lines written one after another without a blank line are joined into one paragraph.

A blank line starts a new paragraph. Write **bold**, _italic_, and [a link](https://example.com).

## A Section Heading

- First item
- Second item

1. Step one
2. Step two

> A blockquote.

![Describe the image](/ui/my-image.png)
```

### Supported Markdown

| Feature        | Syntax                                             |
| -------------- | -------------------------------------------------- |
| Headings       | `#`, `##`, `###`                                   |
| Bold           | `**text**` or `__text__`                           |
| Italic         | `*text*` or `_text_`                               |
| Bold + italic  | `***text***`                                       |
| Links          | `[text](https://...)` (also `mailto:` and `/path`) |
| Images         | `![alt](https://...)` or `![alt](/path.png)`       |
| Unordered list | `- item` or `* item`                               |
| Ordered list   | `1. item`                                          |
| Blockquote     | `> quote`                                          |

Not supported: code blocks, tables, horizontal rules, nested lists, and headings deeper than `###`.

## Good to know

- **Title rule:** keep `# Title` on the first non-empty line. If a file starts with body text instead, that line becomes the title.
- **Date rule:** the date is the modified time, so copying or re-uploading files can change dates. To keep dates stable, set file times when deploying (for example `rsync -t` or `touch -d`).
- **Slug rule:** renaming a file changes its URL.
- **Images and links:** only URLs starting with `http://`, `https://` or `/` (and `mailto:` for links) are turned into HTML. Other forms are left as plain text, which blocks `javascript:` and `data:` links.
- **Safety:** post text is HTML-escaped before it's parsed, so raw HTML in a post is shown as text rather than run.
- **Folder names:** a post can't share a name with a real folder (such as `posts` or `ui`), because real folders are served first.

## License

Use it however you like.
