# DevOrbit 2.0 redesign: notes

## Setup (Laragon)
1. Back up your `openbounty` database.
2. Replace the project files with this package (keep your local `config.php`/`.env`).
3. Run `database/migrations/002_openbounty_2_0.sql` once in HeidiSQL/phpMyAdmin. It is additive and idempotent: adds `bounties.difficulty`, tables `user_profiles` and `notification_reads`, and INSERT IGNOREs a starter skill list. Nothing is dropped or rewritten. Do NOT re-run `schema.sql`.
4. Run `php -l` on every changed file (no PHP binary was available when this was prepared).
5. Open `http://localhost/OpenBounty/`.

Pages degrade gracefully if the migration has not been run (difficulty, profile links, public toggle and mark-as-read are hidden or show a notice).

## Files
New: `includes/ui.php`, `includes/bounty_form.php`, `assets/ob2.css`, `database/migrations/002_openbounty_2_0.sql`, `bounties/{edit,manage,mine}.php`, `reviews/`, `wallet/`, `transactions/`, `developers/`, `leaderboard/`, `notifications/`, `settings/` (each `index.php`).
Rewritten: `includes/header.php` (app shell; landing + auth modal markup kept), `includes/footer.php`, `bounties/{index,view,create,submit}.php`, `dashboard/`, `submissions/`, `payments/`, `profile/`, `skillmentor/` (each `index.php`).
Small edits: `includes/bootstrap.php` (loads ui.php), `bounties/review.php` (safe `return` redirect, no self-review), `assets/app.js` (sidebar collapse, confirm prompts, loading state), `assets/landing.css` (purple palette), `index.php` (hero CTAs).
Untouched: `config.php`, `database/schema.sql`, `auth/*`, `api/health.php`.

## Behavior notes
- Payments: Paiflow is still not connected. Nothing initiates, fabricates or auto-marks payments. Wallet page stores a user-entered public address only (validated `G…` 56 chars) and says plainly it is not a connection; balance shows "Unavailable". Explorer links appear only when a real transaction hash exists.
- Notifications are derived from real submissions, reviews and payments; only read state is stored.
- Earnings/leaderboard use verified payments and approved submissions only; only public profiles are listed. Email and wallet address are never shown to others.
- Bounties with approved work cannot be edited or closed.

## Not done / needs external work
- Mentor booking, sessions and learning projects (SkillMentor shows recommendations only).
- Live wallet connection, balance, and Paiflow payment execution/verification.
- Everything is untested against a live database or browser here: please run the checklist below.

## Test checklist
Register two accounts; post a bounty (A); submit work (B); review it (A, via Reviews and via bounty page); check notifications for both; edit/close/reopen a bounty; save a wallet address; edit profile/skills; verify directory, leaderboard, filters, pagination, grid/list, collapsed sidebar, mobile drawer (≤900px), light/dark toggle.
