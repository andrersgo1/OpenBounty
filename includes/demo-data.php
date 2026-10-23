<?php
declare(strict_types=1);

// ---------------------------------------------------------------------------
// Demo-data layer for the hackathon presentation.
//
// Everything returned by the functions in this file is FICTIONAL fixture
// content. It is rendered only with explicit "Demo" labeling and never
// touches the database: no demo bounties, submissions, payments,
// notifications or users are ever written to real tables, so real
// statistics, real notification unread counts and real payment records
// cannot be inflated or altered by demo content.
//
// Demo bounties reference real skill ids only so the existing skill
// filter chips keep working; they are rendered through the same card
// component as real bounties but always link to the demo detail view.
// ---------------------------------------------------------------------------

function demo_bounties(): array
{
    return [
        'd1' => [
            'title'       => 'Add retry with exponential backoff to the Horizon webhook dispatcher',
            'description' => 'The webhook dispatcher in our open-source ledger notifier gives up after a single failed delivery. Add configurable retries with exponential backoff and jitter, honor Retry-After when present, and log each attempt with a delivery id so operators can trace retries.',
            'requirements' => [
                'Retry at most 5 times with exponential backoff and full jitter',
                'Honor the Retry-After header when the endpoint returns 429 or 503',
                'Every attempt is logged with a shared delivery id',
                'Existing single-attempt behavior is preserved when retries are disabled',
                'Unit tests cover give-up, success-after-retry and header-parsing paths',
            ],
            'reward_xlm'  => '18.0000000',
            'skill_id'    => 4,   // PHP
            'skill'       => 'PHP',
            'difficulty'  => 'intermediate',
            'status'      => 'open',
            'deadline'    => '2026-11-04 17:00:00',
            'created_at'  => '2026-10-21 09:30:00',
            'creator'     => 'Nadia Okafor',
            'creator_role'=> 'Maintainer, ledger-tools (fictional)',
        ],
        'd2' => [
            'title'       => 'Fix race condition in the collaborative editor conflict resolver',
            'description' => 'Under concurrent edits the OT conflict resolver occasionally drops an operation, which shows up as a duplicated paragraph for one collaborator. Reproduce with the included fuzz harness, fix the ordering bug, and keep the existing public API unchanged.',
            'requirements' => [
                'Provide a failing test or fuzz seed that reproduces the drop',
                'Fix preserves total ordering of operations across clients',
                'No changes to the public resolver API',
                'Fuzz harness runs 10k iterations without a divergence',
            ],
            'reward_xlm'  => '42.0000000',
            'skill_id'    => 2,   // TypeScript
            'skill'       => 'TypeScript',
            'difficulty'  => 'advanced',
            'status'      => 'open',
            'deadline'    => '2026-11-12 17:00:00',
            'created_at'  => '2026-10-20 14:05:00',
            'creator'     => 'Theo Lindqvist',
            'creator_role'=> 'Maintainer, collab-docs (fictional)',
        ],
        'd3' => [
            'title'       => 'Build a React settings page with dark-mode toggle and form validation',
            'description' => 'Our dashboard still uses a server-rendered settings form. Rebuild it as a React page: profile fields with inline validation, a persisted dark-mode toggle that follows the existing theme tokens, and accessible error announcements.',
            'requirements' => [
                'Inline validation with clear, announced error messages',
                'Dark-mode toggle persists across reloads using existing theme tokens',
                'Keyboard-navigable and screen-reader friendly',
                'Storybook stories for the form states',
            ],
            'reward_xlm'  => '12.0000000',
            'skill_id'    => 13,  // React
            'skill'       => 'React',
            'difficulty'  => 'beginner',
            'status'      => 'open',
            'deadline'    => '2026-10-29 17:00:00',
            'created_at'  => '2026-10-22 10:45:00',
            'creator'     => 'Priya Raman',
            'creator_role'=> 'Maintainer, dash-ui (fictional)',
        ],
        'd4' => [
            'title'       => 'Write integration tests for the payment-split verification module',
            'description' => 'The module that recomputes the 90/10 payout split has only unit tests. Add integration tests that run it against recorded Horizon effect fixtures, including the duplicate-credit and missing-credit failure cases.',
            'requirements' => [
                'Tests run against checked-in effect fixtures, no network access',
                'Cover: exact split match, duplicate winner credit, missing community credit, unreadable amount',
                'CI job runs the new suite on every pull request',
            ],
            'reward_xlm'  => '15.0000000',
            'skill_id'    => 16,  // Testing
            'skill'       => 'Testing',
            'difficulty'  => 'intermediate',
            'status'      => 'in_review',
            'deadline'    => '2026-11-01 17:00:00',
            'created_at'  => '2026-10-19 08:15:00',
            'creator'     => 'Marcus Webb',
            'creator_role'=> 'Maintainer, payoutkit (fictional)',
        ],
        'd5' => [
            'title'       => 'Document the public REST API with request and response examples',
            'description' => 'The public API ships without reference docs. Write OpenAPI 3.1 descriptions for every public endpoint, with at least one success and one error example per endpoint, generated examples validated in CI.',
            'requirements' => [
                'OpenAPI 3.1 document covers every public endpoint',
                'At least one success and one error example per endpoint',
                'Examples are validated against the schema in CI',
            ],
            'reward_xlm'  => '9.0000000',
            'skill_id'    => 15,  // Documentation
            'skill'       => 'Documentation',
            'difficulty'  => 'beginner',
            'status'      => 'open',
            'deadline'    => null,
            'created_at'  => '2026-10-18 11:20:00',
            'creator'     => 'Elena Vasquez',
            'creator_role'=> 'Maintainer, shipshape-api (fictional)',
        ],
        'd6' => [
            'title'       => 'Migrate legacy jQuery autocomplete to vanilla JavaScript with fetch',
            'description' => 'Replace the jQuery-UI autocomplete on the search page with a dependency-free implementation built on fetch and AbortController, preserving keyboard navigation and the current debounce behavior.',
            'requirements' => [
                'No jQuery or jQuery-UI dependency remains on the search page',
                'Keyboard navigation (arrows, enter, escape) preserved',
                'In-flight requests are cancelled with AbortController on new input',
                'Debounce behavior matches the current 250 ms setting',
            ],
            'reward_xlm'  => '21.0000000',
            'skill_id'    => 1,   // JavaScript
            'skill'       => 'JavaScript',
            'difficulty'  => 'intermediate',
            'status'      => 'open',
            'deadline'    => '2026-11-17 17:00:00',
            'created_at'  => '2026-10-17 16:40:00',
            'creator'     => 'Jonas Berg',
            'creator_role'=> 'Maintainer, storefront-js (fictional)',
        ],
        'd7' => [
            'title'       => 'Add property-based tests to the ledger export crate',
            'description' => 'The CSV export path in our Rust ledger crate is only example-tested. Add property-based tests (proptest) showing round-trip integrity of exported rows, and a differential test against the reference implementation.',
            'requirements' => [
                'proptest suite covers round-trip integrity for random ledgers',
                'Differential test against the reference exporter in CI',
                'Documented seed corpus for regressions',
            ],
            'reward_xlm'  => '30.0000000',
            'skill_id'    => 6,   // Rust
            'skill'       => 'Rust',
            'difficulty'  => 'advanced',
            'status'      => 'open',
            'deadline'    => '2026-11-08 17:00:00',
            'created_at'  => '2026-10-22 19:55:00',
            'creator'     => 'Lena Hoffmann',
            'creator_role'=> 'Maintainer, rust-ledger (fictional)',
        ],
    ];
}

function demo_bounty(string $key): ?array
{
    $all = demo_bounties();
    return $all[$key] ?? null;
}

// Demo developers. Fictional people: they are NOT users rows and never
// appear in real leaderboards, real search, or real reputation.
function demo_developers(): array
{
    return [
        'p1' => [
            'name'     => 'Amara Osei',
            'headline' => 'Backend engineer — PHP & test automation',
            'skills'   => ['PHP', 'Testing', 'SQL'],
            'bio'      => 'Fictional demo profile. Amara is drawn as a backend engineer who maintains payment-adjacent tooling: webhook workers, reconciliation scripts and the integration suites that keep them honest. Her demo story shows how verified bounty payouts build a portable reputation.',
            'portfolio'=> [
                ['title' => 'Webhook delivery worker with replay log', 'desc' => 'Designed the retry model and built the integration suite around recorded HTTP fixtures.'],
                ['title' => 'Reconciliation report generator', 'desc' => 'Turned a manual spreadsheet process into a nightly SQL-driven report with reviewed pull requests.'],
                ['title' => 'Migration safety harness', 'desc' => 'Added pre-flight checks that abort destructive migrations when row counts diverge unexpectedly.'],
            ],
            'completed' => 14,
            'earned_xlm'=> '96.4000000',
            'joined'    => 'November 2025',
        ],
        'p2' => [
            'name'     => 'Kenji Watanabe',
            'headline' => 'Frontend engineer — TypeScript & React',
            'skills'   => ['TypeScript', 'React', 'Node.js'],
            'bio'      => 'Fictional demo profile. Kenji is presented as a product-minded frontend engineer who takes interface bounties from ticket to merged pull request, with an emphasis on accessibility and state machines for tricky UI.',
            'portfolio'=> [
                ['title' => 'Real-time collab cursor layer', 'desc' => 'Shipped the presence UI on top of CRDTs with offline recovery and conflict-free reconnection.'],
                ['title' => 'Design-system form kit', 'desc' => 'Built validated form primitives with screen-reader announcements adopted across three apps.'],
                ['title' => 'Bundle diet initiative', 'desc' => 'Cut first-load JavaScript by 40% via route-level code splitting and dependency audits.'],
            ],
            'completed' => 11,
            'earned_xlm'=> '74.0000000',
            'joined'    => 'January 2026',
        ],
        'p3' => [
            'name'     => 'Lena Hoffmann',
            'headline' => 'Systems engineer — Rust & Go',
            'skills'   => ['Rust', 'Go'],
            'bio'      => 'Fictional demo profile. Lena is sketched as a systems engineer who picks up the hard correctness bounties: property-based testing, differential tests and performance-sensitive parsers.',
            'portfolio'=> [
                ['title' => 'Zero-copy ledger parser', 'desc' => 'Rewrote the hot parsing path with measured 6x throughput gain and fuzzed equivalence proofs.'],
                ['title' => 'Deterministic simulation harness', 'desc' => 'Built a clock-controlled test rig that reproduces timing bugs in the storage engine.'],
            ],
            'completed' => 7,
            'earned_xlm'=> '133.5000000',
            'joined'    => 'August 2025',
        ],
        'p4' => [
            'name'     => 'Rafael Duarte',
            'headline' => 'Tooling & docs — Python',
            'skills'   => ['Python', 'Documentation'],
            'bio'      => 'Fictional demo profile. Rafael represents the maintainer-facing side of bounties: internal tooling, API documentation and the unglamorous work that makes a project approachable to new contributors.',
            'portfolio'=> [
                ['title' => 'API reference overhaul', 'desc' => 'Authored the OpenAPI source of truth and wired example validation into CI.'],
                ['title' => 'Release-note automation', 'desc' => 'Generated changelogs from labeled pull requests, reviewed by maintainers before shipping.'],
            ],
            'completed' => 9,
            'earned_xlm'=> '41.2000000',
            'joined'    => 'March 2026',
        ],
        'p5' => [
            'name'     => 'Sofia Petrova',
            'headline' => 'UI engineer — JavaScript & CSS',
            'skills'   => ['JavaScript', 'HTML/CSS'],
            'bio'      => 'Fictional demo profile. Sofia is presented as a UI engineer focused on dependency reduction and resilient interfaces: removing legacy framework weight while keeping every interaction intact.',
            'portfolio'=> [
                ['title' => 'jQuery elimination program', 'desc' => 'Migrated eleven legacy widgets to vanilla JavaScript with behavioral test coverage.'],
                ['title' => 'Theme token pipeline', 'desc' => 'Unified color and spacing tokens across web and docs sites with automated drift checks.'],
            ],
            'completed' => 12,
            'earned_xlm'=> '58.7000000',
            'joined'    => 'June 2025',
        ],
    ];
}

function demo_developer(string $key): ?array
{
    $all = demo_developers();
    return $all[$key] ?? null;
}

// Example skill-match recommendations between fictional developers and
// demo bounties. Each entry explains WHY the match was made.
function demo_recommendations(): array
{
    return [
        ['developer' => 'p1', 'bounty' => 'd4',
         'why' => 'Amara lists Testing and PHP; this bounty is a testing task for a payment-split module — a direct 2-skill match on a payment-adjacent codebase.'],
        ['developer' => 'p2', 'bounty' => 'd2',
         'why' => 'Kenji lists TypeScript; the race condition sits in a TypeScript conflict resolver, and his portfolio shows CRDT presence work.'],
        ['developer' => 'p5', 'bounty' => 'd6',
         'why' => 'Sofia lists JavaScript and HTML/CSS; the task is a vanilla-JS migration, exactly her jQuery-elimination specialty.'],
        ['developer' => 'p4', 'bounty' => 'd5',
         'why' => 'Rafael lists Documentation and Python; the API docs bounty matches both his skills and his portfolio of reference overhauls.'],
        ['developer' => 'p3', 'bounty' => 'd7',
         'why' => 'Lena lists Rust; the property-based testing bounty targets her core strength in correctness tooling.'],
        ['developer' => 'p1', 'bounty' => 'd1',
         'why' => 'Amara\'s PHP and webhook-delivery portfolio map directly onto the Horizon dispatcher retry task.'],
    ];
}

// Illustrative activity for the demo feed. None of these events happened;
// each is labeled as demo activity wherever it is rendered.
function demo_activity(): array
{
    return [
        ['icon' => 'flag',  'text' => 'Demo bounty “Add property-based tests to the ledger export crate” was published by Lena H. (fictional maintainer).', 'at' => '2026-10-23 01:10:00'],
        ['icon' => 'spark', 'text' => 'A demo submission for “Write integration tests for the payment-split verification module” entered review.', 'at' => '2026-10-22 21:40:00'],
        ['icon' => 'check', 'text' => 'A demo review on “Migrate legacy jQuery autocomplete…” was completed with changes requested and reviewer notes.', 'at' => '2026-10-22 18:05:00'],
        ['icon' => 'users', 'text' => 'Demo developer Kenji W. (fictional) updated his profile, adding Node.js to his skills.', 'at' => '2026-10-22 15:30:00'],
        ['icon' => 'compass', 'text' => 'A demo recommendation was generated for Amara O. — matched to a Testing-tagged bounty from her skills.', 'at' => '2026-10-22 12:20:00'],
        ['icon' => 'tiles', 'text' => 'Demo bounty “Build a React settings page with dark-mode toggle” reached 3 (fictional) watchers.', 'at' => '2026-10-22 09:00:00'],
    ];
}

// Standard demo-content notice, used at the top of every demo surface.
function demo_notice(string $what): string
{
    return '<div class="ob-note" style="margin-bottom:18px"><div><strong>Demo content.</strong> '
        . e($what)
        . ' Nothing here is a real user action, and demo items cannot create submissions, payments or notifications.</div></div>';
}

// Filter the demo bounty fixtures with the same semantics as the real
// bounties index (q / status group / difficulty / deadline window) and
// return rows shaped exactly like real query rows plus is_demo/demo_key,
// so they can render through ob_bounty_card().
function demo_bounty_matches(array $filters): array
{
    $out = [];
    foreach (demo_bounties() as $key => $b) {
        if (($filters['q'] ?? '') !== '') {
            $q = mb_strtolower((string)$filters['q']);
            $hay = mb_strtolower($b['title'] . ' ' . $b['description'] . ' ' . $b['skill']);
            if (mb_strpos($hay, $q) === false) continue;
        }
        $st = $b['status'];
        if (($filters['status'] ?? '') === 'open' && $st !== 'open') continue;
        if (($filters['status'] ?? '') === 'in_review' && $st !== 'in_review') continue;
        if (($filters['status'] ?? '') === 'completed' && !in_array($st, ['approved', 'payment_pending', 'paid'], true)) continue;
        if ((int)($filters['skill'] ?? 0) > 0 && (int)$b['skill_id'] !== (int)$filters['skill']) continue;
        if (($filters['difficulty'] ?? '') !== '' && $b['difficulty'] !== $filters['difficulty']) continue;
        $dl = $b['deadline'];
        $win = $filters['deadline'] ?? '';
        if ($win === 'none') { if ($dl !== null) continue; }
        elseif ($win === 'week' || $win === 'month') {
            if ($dl === null) continue;
            $t = strtotime($dl); if (!$t) continue;
            $max = $win === 'week' ? 7 * 86400 : 30 * 86400;
            if ($t <= time() || $t - time() > $max) continue;
        }
        $out[] = [
            'demo_key'   => $key,
            'is_demo'    => 1,
            'id'         => 0,
            'title'      => $b['title'],
            'description'=> $b['description'],
            'reward_xlm' => $b['reward_xlm'],
            'status'     => $b['status'],
            'deadline'   => $b['deadline'],
            'skill'      => $b['skill'],
            'skill_id'   => $b['skill_id'],
            'difficulty' => $b['difficulty'],
            'creator'    => $b['creator'],
            'uid'        => 0,
            'created_at' => $b['created_at'],
        ];
    }
    return $out;
}

// Sort comparator mirroring the SQL ORDER BY of the real bounties index.
function demo_bounty_compare(string $sort): callable
{
    $statusOrder = ['open' => 0, 'in_review' => 1, 'approved' => 2, 'payment_pending' => 3, 'paid' => 4];
    return function (array $a, array $b) use ($sort, $statusOrder): int {
        $ra = (float)$a['reward_xlm']; $rb = (float)$b['reward_xlm'];
        if ($sort === 'reward_desc') return $ra === $rb ? strcmp((string)($b['demo_key'] ?? ''), (string)($a['demo_key'] ?? '')) : ($ra < $rb ? 1 : -1);
        if ($sort === 'reward_asc')  return $ra === $rb ? strcmp((string)($b['demo_key'] ?? ''), (string)($a['demo_key'] ?? '')) : ($ra > $rb ? 1 : -1);
        if ($sort === 'deadline') {
            if (($a['deadline'] ?? null) !== ($b['deadline'] ?? null)) return ($a['deadline'] ?? null) === null ? 1 : -1;
            $c = strcmp((string)($a['deadline'] ?? ''), (string)($b['deadline'] ?? ''));
            if ($c !== 0) return $c;
            return strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? ''));
        }
        $sa = $statusOrder[$a['status']] ?? 9; $sb = $statusOrder[$b['status']] ?? 9;
        if ($sa !== $sb) return $sa <=> $sb;
        return strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? ''));
    };
}
