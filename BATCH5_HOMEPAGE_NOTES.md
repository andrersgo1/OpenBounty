# Batch 5: Homepage redesign

The public homepage (`index.php`) now follows the openbounty.ai look (navy canvas, top nav, filterable feed,
"how it works", split sections with example panels) while keeping the original hero and Open bounties card.

## What changed
- `index.php`: new homepage. Hero + feed card kept. Added a filterable feed (status, skill, sort), How it works,
  For creators, For contributors, closing call to action. All numbers and rows come from the database.
- `assets/landing.css` (new): homepage-only styling, dark navy by default with a navy-tinted light theme.
- `includes/header.php`: landing nav (Bounty feed, How it works, For creators, For contributors, Log in, Post a bounty)
  and the sign-in modal markup. The modal CSS and JS already existed but the markup was missing, so
  "Log in" / "Post a bounty" fell through to the old login page.
- `includes/footer.php`: landing footer with links to every feature page.
- `auth/login.php`, `auth/register.php`: no separate pages any more. GET opens the modal; a failed attempt reopens
  the modal with the error. After login the person returns to where they were heading (validated by `safe_path()`).
- `includes/bootstrap.php`: `require_login()` now passes the page to return to; added `safe_path()` and `xlm()`.
- `assets/app.js`: fixed the modal's "Log in / Create an account" switch link (it stopped working after one click);
  remembers the page a guest was trying to reach.
- `assets/theme.js`: supports the icon-only theme button.

## Not changed
Database schema, config, the other feature pages, and payments (still not connected to Paiflow).

## Test checklist (Laragon)
1. `http://localhost/OpenBounty/` loads with the new design, signed out.
2. Log in / Post a bounty open the modal. A wrong password reopens it with an error.
3. Sign up from the modal, then confirm you land on the dashboard (or the page you were heading to).
4. Feed filters: status, skill, sort, and "Clear filters" when nothing matches.
5. Resize to phone width; toggle the theme.
PHP syntax was checked by inspection only (no PHP binary was available when this was prepared); run `php -l` on the changed files.
