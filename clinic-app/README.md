# Clinic Portal / MedScribe — source mirror

This folder mirrors the live `public_html` directory served at
`app.medscribeai.org` / `app.medscribeai.com` (Beget hosting). It contains
the PHP backend, front-end templates, and styling for two related apps that
share a hosting account:

- **Clinic Portal** (`index.php`, `auth.php`, `db.php`, `api.php`) — login/signup,
  member rooms, and a live chat between clinic owners and members (SQLite-backed).
- **MedScribe** (`app.php`, `files.php`, `style.css`) — a doctor-facing tool for
  recording consultations, transcribing them, and generating structured medical
  records via an AI API.

## ⚠️ `config.php` is intentionally NOT included

`config.php` holds the live **DeepSeek API key** and must never be committed —
anyone with that key could rack up usage on this account. It is excluded via
`.gitignore`.

To run this app yourself:

1. Copy `config.example.php` to `config.php`.
2. Open `config.php` and replace the placeholder `DEEPSEEK_API_KEY` (and any
   other placeholder values) with your own real DeepSeek API credentials.
3. `config.php` will then be picked up automatically by `test.php` / `app.php`
   and stays untracked thanks to `.gitignore`.

## File layout

```
.htaccess           — hardened Apache rules: blocks direct access to db.php,
                       config.php, test.php, /data, backup files, dotfiles, etc.
index.php           — Clinic Portal router (login / signup / dashboard / chat)
auth.php            — POST handler for login, signup, logout
db.php              — SQLite connection + schema + query helpers
api.php             — AJAX/JSON endpoints for the chat (messages, members)
app.php             — MedScribe doctor UI (recording, transcription, AI processing)
files.php           — JSON listing of a doctor's saved reports/transcripts
logout.php          — session destroy + redirect
style.css           — shared dark-theme styling for both apps
test.php            — diagnostic page (PHP version, includes check) — blocked
                       from public access by .htaccess; safe to delete entirely
config.example.php  — placeholder template; copy to config.php and fill in
.gitignore          — keeps config.php (and local data/uploads) out of git
```

## Notes on this mirror

- `db.php` auto-creates a SQLite database at `/data/clinic.db` on first run —
  that directory is git-ignored since it holds runtime/user data.
- A handful of obvious typos in the live `index.php` / `app.php` source were
  corrected while transcribing (e.g. malformed `</span>` tags, an undefined
  `v.online`/`v.joined` reference fixed to `m.online`/`m.joined`, and a broken
  string-concatenation in `index.php`). Functionally the app behaves the same;
  these are minor cleanups over the live version.
- A few decorative section-divider comments in `app.php` and `style.css`
  contained corrupted bytes on the live server (not a transcription artifact —
  verified the corruption exists in the original files). These were restored
  to clean ASCII/Unicode dividers; no functional code was affected.
