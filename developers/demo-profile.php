<?php
require __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/demo-data.php';

$key = (string)($_GET['id'] ?? '');
$d = demo_developer($key);
if (!$d) {
    http_response_code(404);
    $pageTitle = 'Example profile not found';
    require __DIR__.'/../includes/header.php';
    echo ob_page_head('Example profile not found', 'This fictional example does not exist.');
    echo ob_empty('search', 'No such example profile', 'Browse the developers directory for real members and example profiles.', '<a class="ob-btn" href="/OpenBounty/developers/">Developers directory</a>');
    require __DIR__.'/../includes/footer.php';
    exit;
}

$pageTitle = $d['name'] . ' (example profile)';
require __DIR__.'/../includes/header.php';
?>
<?= ob_page_head($d['name'], $d['headline']) ?>
<?= demo_notice('This is a fictional example profile created for the hackathon demo. ' . $d['name'] . ' is not a registered DevOrbit user; the biography, portfolio and statistics below are illustrative and do not appear in real rankings or reputation.') ?>

<div class="ob-split">
  <section class="ob-stack">
    <section class="ob-card">
      <div style="display:flex;gap:14px;align-items:center;margin-bottom:14px">
        <span class="ob-avatar md" style="width:56px;height:56px;font-size:20px"><?= e(ob_initials($d['name'])) ?></span>
        <div>
          <h3 style="margin:0"><?= e($d['name']) ?> <span class="ob-badge amber" style="vertical-align:middle">Fictional</span></h3>
          <div class="ob-muted" style="font-size:12.5px"><?= e($d['headline']) ?> · Example member since <?= e($d['joined']) ?></div>
        </div>
      </div>
      <div class="ob-tags" style="margin-bottom:14px"><?php foreach ($d['skills'] as $sn): ?><span class="ob-skill"><?= e($sn) ?></span><?php endforeach; ?></div>
      <p style="line-height:1.7;margin:0"><?= e($d['bio']) ?></p>
    </section>
    <section class="ob-card">
      <h3>Example portfolio</h3>
      <p class="ob-muted" style="margin:4px 0 12px;font-size:13px">Fictional portfolio entries showing how completed bounties read on a mature profile.</p>
      <ul class="ob-feed">
        <?php foreach ($d['portfolio'] as $item): ?>
        <li><span class="dot"></span><div><strong><?= e($item['title']) ?></strong><br><small><?= e($item['desc']) ?></small></div></li>
        <?php endforeach; ?>
      </ul>
    </section>
  </section>
  <aside class="ob-stack">
    <section class="ob-card">
      <h3>Demo reputation</h3>
      <div class="ob-grid ob-g2" style="gap:12px;margin-top:10px">
        <div><span class="ob-muted" style="font-size:11.5px">Completed bounties</span><div style="font-weight:700;font-size:22px"><?= (int)$d['completed'] ?></div></div>
        <div><span class="ob-muted" style="font-size:11.5px">Earned (illustrative)</span><div style="font-weight:700;font-size:22px"><?= e(xlm($d['earned_xlm'])) ?> <small class="ob-muted">XLM</small></div></div>
      </div>
      <p class="ob-muted" style="margin:12px 0 0;font-size:12.5px">Illustrative numbers only. Real reputation is computed from approved submissions and verified payments in the database — this profile has neither, by design.</p>
    </section>
    <section class="ob-card">
      <h3>Why this example exists</h3>
      <p class="ob-muted" style="margin:4px 0 0;font-size:13.5px;line-height:1.7">A new marketplace has no history to show. This fictional profile demonstrates the destination: a developer whose reputation is backed by payouts DevOrbit independently verified on Stellar Testnet.</p>
      <div style="display:flex;gap:8px;margin-top:14px;flex-wrap:wrap">
        <a class="ob-btn ghost sm" href="/OpenBounty/developers/">All developers</a>
        <a class="ob-btn ghost sm" href="/OpenBounty/skillmentor/">Demo recommendations</a>
      </div>
    </section>
  </aside>
</div>
<?php require __DIR__.'/../includes/footer.php'; ?>
