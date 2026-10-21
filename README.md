# deadSimple Blog

A tiny flat-file blog written in plain PHP. No database, no framework, no build step. Write a Markdown file, drop it in `posts/`, and it's live. Visitors can like posts, with no account needed.

## How it works

1. You add a Markdown file to the `posts/` folder, for example `posts/post-one.md`.
2. The **file name is the slug**, so that post lives at `domain.com/post-one`.
3. The **first line of the file is the title** (for example `# My First Post`). Everything after it is the post body.
4. The **date** comes from an optional `Date:` line right under the title. Without one, it falls back to the file's last-modified time.
5. The homepage lists all posts, newest first, 10 per page, with Previous / Next links.
6. When someone opens `/post-one`, the web server rewrites the URL to `post.php?slug=post-one`. `post.php` reads `posts/post-one.md`, converts the Markdown to HTML with a small built-in parser, and renders the page.
7. Each post ends with a Like button. Like counts are stored in `data/likes.json`, not in the post files.

```
Request: /post-one
    -> .htaccess (Apache) or router.php (localhost)
    -> post.php?slug=post-one
    -> reads posts/post-one.md
    -> parses Markdown -> HTML page
    -> reads data/likes.json -> shows the like count
```

## Project structure

```
deadSimple-blog/
├── index.php        Homepage: profile header, post list, pagination
├── post.php         Single post page: renders a .md file, handles likes
├── router.php       Router for PHP's built-in server (localhost only)
├── .htaccess        Pretty-URL rewrite rule for Apache (live server)
├── .gitignore       Contains "data/" so likes are never committed
├── posts/           Your Markdown posts, one file per post
│   └── post-one.md
├── data/            Created automatically on the first like (private)
│   ├── likes.json   Like counts and per-visitor limits
│   ├── likes.lock   Lock file so two likes at once don't clash
│   ├── secret.key   Random key used to hash visitor IPs
│   └── .htaccess    Blocks web access to this folder (Apache)
└── ui/
    └── avatar.png   Profile picture shown in the header
```

### File by file

**`index.php`**
The homepage. It finds every `posts/*.md` file, reads each post's title and date, sorts them newest first (ties are broken by file name so the order never jumps around), and shows 10 per page. The title is the first non-empty line (leading `#` marks are removed). The date comes from the `Date:` line, or the file's modified time if there is none. If a file has no usable title, it falls back to a prettified slug (`post-one` becomes "Post One"). Page numbers come from `?page=2`, `?page=3`, and so on.

**`post.php`**
Renders one post and handles likes. It reads the slug from `$_GET['slug']`, allows only letters, numbers, `-` and `_` (which blocks `../` path tricks), and loads `posts/<slug>.md`. A missing post returns a real 404. It contains the Markdown parser, the like logic and the page layout.

**`router.php`**
Only used on localhost with PHP's built-in server, which doesn't read `.htaccess`. It does the same job as the Apache rule: real files and folders are served as they are, `/` loads `index.php`, any valid slug loads `post.php`, and anything else returns 404. It should also return 404 for `/data` so the like data stays private on localhost.

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

**`data/`**
Created by `post.php` the first time anyone likes a post. It holds everything the likes feature needs. See "Likes" below.

**`ui/avatar.png`**
The round profile picture in the header of both pages.

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
4. Make sure the project folder is writable by the web server, so `data/` can be created and likes can be saved.
5. Add `data/` to `.gitignore`, and keep it out of any "delete extra files" step in your deploy (see "Likes" below).

`router.php` isn't used on Apache. It's harmless to leave in place.

## Writing a post

Create `posts/my-post.md` (lowercase letters, numbers, `-` and `_` only; no spaces):

```markdown
# Your Post Title Here

Date: 2026-10-20 14:30

First paragraph. Lines written one after another without a blank line are joined into one paragraph.

A blank line starts a new paragraph. Write **bold**, _italic_, and [a link](https://example.com).

## A Section Heading

- First item
- Second item

1. Step one
2. Step two

> A blockquote.

---

![Describe the image](/ui/my-image.png)
```

The `Date:` line is optional but recommended. It must be right under the title. Add a time (`14:30`) to control the order of posts published on the same day.

### Supported Markdown

| Feature         | Syntax                                             |
| --------------- | -------------------------------------------------- |
| Headings        | `#` to `######`                                    |
| Bold            | `**text**` or `__text__`                           |
| Italic          | `*text*` or `_text_`                               |
| Bold + italic   | `***text***`                                       |
| Links           | `[text](https://...)` (also `mailto:` and `/path`) |
| Images          | `![alt](https://...)` or `![alt](/path.png)`       |
| Unordered list  | `- item` or `* item`                               |
| Ordered list    | `1. item`                                          |
| Blockquote      | `> quote`                                          |
| Horizontal line | `---` (also `***` or `___`) alone on its own line  |

Not supported: code blocks, tables and nested lists.

## Likes

Every post ends with a Like button and a count. There are no accounts or logins.

### How it works

- Clicking **Like** sends a normal form post to the same page. `post.php` adds 1 to that post's count, then redirects back to the post. No JavaScript is needed.
- Counts live in `data/likes.json`, keyed by post slug. The post files are never changed, so pushing an edited post never resets its likes.
- **Each visitor can like each post up to 10 times.** After that the button is disabled.
- A visitor is identified by a hash of their IP address and the post slug, made with a secret key stored in `data/secret.key`. The raw IP address is never stored.
- A lock file (`data/likes.lock`) makes sure two likes at the same moment don't overwrite each other.

`data/likes.json` looks like this:

```json
{
  "counts": { "post-one": 12, "post-two": 3 },
  "visitors": { "9f2c...": 2, "b71a...": 10 }
}
```

You can fix or reset a count by editing the number under `counts`.

### What to take care of

- **Keep `data/` out of Git.** Add `data/` to `.gitignore`, so your counts, secret key and visitor limits never go into the repo or get overwritten by it.
- **Don't wipe `data/` when you deploy.** A plain `git pull` leaves it alone. Tools that mirror the repo exactly (`rsync --delete`, a fresh checkout, a new container) delete it unless you exclude `data/`. If it's deleted, all counts start again from zero.
- **Back it up.** Copy `data/likes.json` now and then.
- **Write permission.** The web server must be able to create and write `data/`. If it can't, the Like button silently does nothing.
- **Keep `data/` private.** `post.php` creates a `.htaccess` inside it that denies web access on Apache. On localhost, `router.php` must return 404 for `/data`. On nginx, which ignores `.htaccess`, add a rule that blocks the `data/` folder.
- **The limit is per IP address.** Everyone on the same office or mobile network shares one set of 10 likes per post, and a VPN lets someone get around the limit. Treat likes as friendly, not tamper-proof.
- **Behind Cloudflare or another proxy,** every visitor can look like the same IP, so they would all share one limit of 10. Use the real client IP header instead (`HTTP_CF_CONNECTING_IP` for Cloudflare), and only if all traffic really goes through the proxy.
- **Renaming a post** (changing its file name) changes its slug, so it starts again from zero likes. Rename the key in `likes.json` to keep them.
- **Deleting a post** leaves its count in `likes.json`. It's harmless, and you can remove it by hand.
- **The limit is a code setting.** Change `MAX_LIKES_PER_VISITOR` at the top of the likes section in `post.php` to allow more or fewer likes.

## Good to know

- **Title rule:** keep `# Title` on the first non-empty line. If a file starts with body text instead, that line becomes the title.
- **Date rule:** use a `Date:` line right under the title so dates survive copying, uploading and Git deploys. Without it, the date is the file's modified time, which many uploads and deploys reset, so several posts can end up with the same date.
- **Slug rule:** renaming a file changes its URL.
- **Images and links:** only URLs starting with `http://`, `https://` or `/` (and `mailto:` for links) are turned into HTML. Other forms are left as plain text, which blocks `javascript:` and `data:` links.
- **Safety:** post text is HTML-escaped before it's parsed, so raw HTML in a post is shown as text rather than run.
- **Folder names:** a post can't share a name with a real folder (such as `posts`, `ui` or `data`), because real folders are served first.

## License

Use it however you like.
