# DevOrbit

DevOrbit combines open-source task bounties and evidence-based rewards with **SkillMentor**, a skill-based mentorship experience. This package is being built in tested batches.

## Batch 2 status

Implemented in this batch:
- PHP + PDO database connection for MySQL/MariaDB (Laragon-friendly)
- Registration with password hashing and unique-email handling
- Login/logout using PHP sessions, session ID rotation, and CSRF protection
- Member-only self-registration; privileged roles must be assigned by a trusted administrator directly in the database until an admin-management workflow is built
- Authenticated dashboard with database-backed counts
- Basic audit events for registration/login/logout
- JSON database health endpoint at `/OpenBounty/api/health.php`
- Existing UI retained at `/OpenBounty/public/prototype.html`

Not yet implemented: bounty CRUD/submissions/reviews, mentor discovery/booking, wallet signing, Paiflow execution, transaction verification, payout verification, production deployment hardening. The health endpoint explicitly does not claim Paiflow is connected.

## Install using Laragon (Windows)

1. Extract this ZIP into `C:\laragon\www\OpenBounty\` so `index.php`, `config.php`, `database`, `auth`, and `dashboard` are directly inside that folder.
2. Start **Apache** and **MySQL** in Laragon.
3. Open HeidiSQL (or phpMyAdmin), connect to the local MySQL server, open `database/schema.sql`, and run it once to create the `openbounty` database and tables. This is for a fresh database; back up data before changing any existing database.
4. Check `config.php`. Defaults match a typical local Laragon setup: host `127.0.0.1`, port `3306`, database `openbounty`, user `root`, blank password. If your MySQL has a password, set it in `config.php` locally or provide `DB_PASSWORD` as an environment variable. Do not commit secrets.
5. Visit `http://localhost/OpenBounty/`.
6. Select **Create account**, register a test account, and confirm the dashboard appears.
7. Test login and logout. To check DB connectivity, visit `http://localhost/OpenBounty/api/health.php`; expected response includes `"ok":true` and `"database":"connected"`.

## Important security notes

- Never commit `.env`, real tokens, private keys, or wallet recovery phrases. `config.php` contains only local development defaults; use server environment variables for hosted environments.
- Public registration can only create `member` accounts. Do not allow a public form to assign `admin` or `reviewer` roles.
- Use HTTPS, secure session settings, rate limiting, backup/restore testing, and an operational secret manager before public deployment.
- This build does not make or initiate any blockchain transaction. Paiflow must be integrated only after the supported SDK/API contract and deployment configuration have been checked.
- Never mark a bounty paid solely because the original transaction reports success; independently verify every expected payout, recipient, and amount.

## Folder overview

- `index.php` — entry page
- `auth/` — registration, login, logout
- `dashboard/` — authenticated dashboard
- `includes/` — session, PDO, CSRF, audit, layout helpers
- `database/schema.sql` — initial schema
- `api/health.php` — database health check
- `public/prototype.html` and `assets/` — UI prototype and styling
- `config.php` — local database settings
- `.env.example` — future deployment configuration template


## Batch 3: Bounty workflow

The PHP/MySQL backend now includes authenticated bounty creation (draft or publish), marketplace search, bounty details, evidence URL submissions, and creator/reviewer decisions (approve, request changes, reject). These features use the existing `bounties`, `submissions`, `users`, `skills`, and `audit_logs` tables from `database/schema.sql`; do not re-import the schema over your existing database.

### Test Batch 3
1. Log in at `http://localhost/OpenBounty/auth/login.php`.
2. Open `http://localhost/OpenBounty/bounties/` and create a bounty with a reward and acceptance criteria.
3. Use a second member account to submit an HTTP(S) evidence URL to the bounty. A bounty creator cannot submit to their own bounty.
4. Log back in as the bounty creator and approve, request changes, or reject the submission.
5. Confirm the submission status is shown on the bounty detail page.

**Payment safety:** approving a submission does not transfer funds and does not mark the bounty paid. Paiflow remains unconfigured until its supported execution and verification flow is implemented in a later batch. Only the bounty creator or an account whose role is `reviewer` or `admin` can review submissions. New public registrations remain `member`; don't promote roles by editing the public form.


## DevOrbit 2.0 interface update

The public-facing interface now uses a navy-first design with a persistent sidebar and header actions. Visitors can explore the bounty marketplace and bounty details, read feature previews for submissions, payment proof, developer profiles, and SkillMentor, and open login or account creation in a header/modal flow. Actions such as posting a bounty or expressing interest prompt visitors to authenticate. The existing PHP/MySQL workflow and its current integration limitations remain unchanged; Paiflow payment execution is still not connected.
