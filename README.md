# Lonny's Learning Playground

A self-hostable, English-language website for Lonny: teacher training, language experiences, Suggestopedia, real teaching demonstrations, and a *moderated* community. The current v2 design uses restrained editorial typography, cool-white backgrounds, deep blue and dark-green accents, and a light but mature educational atmosphere.

## What works

- Home, Meet Lonny, Workshops, Watch & Learn, Monthly Experiment, Ask Lonny, Playground, Contact, Privacy.
- Responsive navigation and a browser-based 90-second Sound Shapes experiment.
- Private storage of workshop enquiries, questions, and workshop registration requests. Visitors receive a truthful **request received** message, not a false booking confirmation.
- Moderated question publishing (only published question + answer appear publicly; no email addresses).
- Moderated word wall; user submissions do not appear until approved.
- Workshop formats and dated sessions; public registration request form appears only for *real future sessions* entered in Admin.
- Private admin sign-in; view/mark/delete enquiries and registrations, respond to questions, moderate word wall, edit workshops and dates, add YouTube/Vimeo links, upload real photos, change key text, change password.
- One-time first-admin setup with a strong **private installation key**, not an insecure "first visitor becomes admin" shortcut.
- No external database service required. Information is persisted as a locked JSON file *outside* the public web directory.

## Important: Before going live

This is a working starter site, not a fully launched production service. Lonny must supply and review his own photographs, videos, workshop descriptions/dates, and a completed privacy notice. There are **no automatic email alerts or newsletters**. Incoming requests sit in the Admin area until reviewed. Arrange backups of `app/storage/site.json` and `public/media/` (at least daily). Add abuse protection such as Turnstile if traffic increases. Confirm privacy/consent, retention policies, processors, and data controller identity with whoever will operate the site. Use HTTPS, keep PHP patched, and check hosting logs.

## Hosting requirements

- PHP **8.1+**, with standard `session`, `fileinfo`, `iconv`, and JSON support.
- A normal web host that lets the website's **document root point to `public/`** (or lets you place `public/` in `public_html` and `app/` in the private parent folder).
- Writable `app/storage/` and `public/media/` directories; on most hosts 0750/0755 permissions with the PHP worker as owner are enough.
- HTTPS certificate for the real domain.
- **No Node runtime, Supabase account or WordPress installation required.** The app uses PHP and locked private JSON storage. This simplifies handoff but is intended for a small/moderate audience, not a high-traffic social network.

## Installation

1. Unzip the project somewhere private. Its structure must remain:

   ```text
   site/
   ├── app/
   │   ├── core.php
   │   ├── admin_view.php
   │   ├── private.php         ← setup secret (private)
   │   └── storage/            ← user data (private)
   ├── public/                 ← domain's document root
   │   ├── index.php
   │   ├── assets/
   │   └── media/
   ├── SETUP_KEY.txt           ← keep privately; do NOT upload to public/
   └── README.md
   ```

2. Point the domain's document root to `site/public/`. If the host insists on `public_html`, upload **only the contents of `public/`** into `public_html/` and place `app/` in its parent directory. Ensure `public_html/index.php` can reach `../app/core.php`.
3. Visit `https://your-domain.example/?page=setup`. Use the one-time key from **SETUP_KEY.txt** to create the administrator account. Choose **Lonny's email** and a password of 12+ characters. The key stops working once an account exists.
4. Visit `?page=admin` to maintain the site. Begin with Photos, Videos, Workshops and Website copy. Complete the privacy fields and replace the draft privacy notice.
5. Add real dated sessions only when they are confirmed. You can temporarily close registration by unchecking the open checkbox in Admin.
6. Open all public routes from desktop and mobile and send a test enquiry, question, and moderated word-wall entry to confirm persistence and publication workflows. Test email contact by *replying manually* from Lonny's mail provider.
7. Back up `app/storage/` and `public/media/` before any update. Never expose the private `app/` folder through the public web root.

For development, inside the unzipped project run `php -S 127.0.0.1:8080 -t public` and open `http://127.0.0.1:8080`.

## Lonny's realistic upkeep routine

| Time per week | Activity |
|---:|---|
| 30 minutes | Answer one selected question. |
| 30 minutes | Moderate the word wall and check visitor questions. |
| 45 minutes | Upload a photo/video, refine a workshop, or refresh a short experience. |
| 15 minutes | Review workshop enquiries and session requests. |

**Monthly:** refresh the featured experiment prompt and share one short video demonstration, if available. The underlying interactive Sound Shapes activity is the same until a developer changes its JavaScript; the prompt is editable through Admin. Avoid launching an unmoderated, open-ended discussion forum until Lonny has more time for moderation.

## Pages and content

- `?page=home` — home and three audience entryways
- `?page=about` — exact approved biography, philosophy, published article link
- `?page=workshops` — tracks, formats and real dated session registration requests
- `?page=watch` — only actual uploaded images and approved YouTube/Vimeo videos
- `?page=experiment` — original sound symbolism activity
- `?page=ask` — questions, published answers
- `?page=playground` — moderated imaginative word wall
- `?page=contact` — workshop enquiries
- `?page=privacy` — **draft to replace**
- `?page=admin` — private maintenance interface

## Limitations and next upgrades

- No newsletter delivery, booking confirmation email, payment collection, mailing list, CMS integration, or calendar sync is connected.
- The monthly interactive **game code** is currently fixed; Lonny can edit its title/prompt and moderate visitor ideas himself. Making entirely new game types without coding would require a more advanced activity builder.
- JSON file storage is simple and works for moderate traffic, but for sustained high volume, collaborative moderation, advanced search or many concurrent submissions, migrate to a proper database with backups and stronger server-side abuse controls.
- The privacy page is not a legal substitute for a customized policy; there is deliberately no fabricated physical address or controller name.
- Dates use the web host's configured timezone; confirm it matches Lonny's event location and show the timezone explicitly if you schedule international sessions.

## About the separate Lovable prototype

A separate visual project was also initiated in Lovable, but its build remained incomplete when the Lovable workspace ran out of credits. **Do not treat that preview as the working website.** This downloadable PHP package is independent and does not need Lovable.

## Visual redesign — v2 (November 2026)

- Design direction: intellectual + creative, contemporary, calm. Creativity approximately 3/5.
- Typography: **Newsreader** for restrained editorial headlines; **Inter** for interface and body copy. Original oversized Syne headline treatment removed through `public/assets/editorial.css`.
- Palette: bright cool-white `#F7F9F8`, deep blue `#244D67`, forest green `#285648`, teal `#5F9192` and restrained amber `#E3B85B`. No pastel rainbow.
- Homepage replaced the “hated school” opening with a neutral education/communication message. About biography is marked provisional and editable under Admin → Website copy. Replace it when the new bio arrives.
- Photo slots use the public portrait URL on [Lonny's existing website](https://www.lonnygold.training/lonny-gold-2/) as an optional reference, but remote image access has not been reliable in the development sandbox. If the image cannot load, the site now presents an intentional editorial fallback, not an empty rectangle. For a reliable production result, obtain **original photographs from Lonny with consent** and upload them through Admin → Photos. His current About page shows a portrait, not a gallery of large workshop photographs.
- The experimental activity, moderated Q&A, workshops, private messages and administration remain intact.

## Publishing to GitHub safely

The downloadable archive includes an installation secret (`app/private.php` and `SETUP_KEY.txt`) for a standalone private deployment. **Never publish those files in GitHub.** `.gitignore` excludes them, but if they were previously tracked, remove them from Git history and rotate the installation key immediately. Commit `app/private.example.php` instead. Private data under `app/storage` and photo uploads under `public/media` must also remain untracked. Add a deployment-specific `private.php` on each server, and store the setup key only in the deployment owner's private password manager.

Do not publish or point the live domain to the project until a production privacy notice, legal data-controller details, secure administrator account, backups, hosting configuration and real media are reviewed.

### Create the private configuration after cloning the GitHub repository

For a deployment from the GitHub-safe source archive, on the **server or local machine**:

1. Generate a cryptographically random key: `php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'`
2. Copy `app/private.example.php` to `app/private.php` and replace its `setup_key` value with the key.
3. Record the key securely, outside GitHub; it is needed only when creating the administrator account via `?page=setup`.
4. Keep the `app/` directory outside the public document root and allow PHP to write only the private storage and public media folders.

For a ZIP installed privately rather than via GitHub, the included installation key is ready to use, but rotate it if the ZIP was shared publicly.