<?php
require __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/demo-data.php';

$key = (string)($_GET['id'] ?? '');
$b = demo_bounty($key);
if (!$b) {
    http_response_code(404);
    $pageTitle = 'Demo bounty not found';
    require __DIR__.'/../includes/header.php';
    echo ob_page_head('Demo bounty not found', 'This demo listing does not exist.');
    echo ob_empty('search', 'No such demo bounty', 'The example you followed is not part of the demo set. Browse the board for current bounties.', '<a class="ob-btn" href="/OpenBounty/bounties/">Explore bounties</a>');
    require __DIR__.'/../includes/footer.php';
    exit;
}

$u = current_user();
$pageTitle = $b['title'];
require __DIR__.'/../includes/header.php';
?>
<?= ob_page_head($b['title'], 'A worked example of what a well-specified bounty looks like.') ?>
<?= demo_notice('This is a fictional demo bounty used to make the marketplace understandable during the hackathon demo. It is not a real listing: it has no real creator account, and it cannot accept submissions, reviews or payments.') ?>

<div class="ob-split">
  <section class="ob-card">
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">
      <span class="ob-badge amber">Demo</span>
      <?= ob_badge($b['status']) ?>
      <?= ob_diff($b['difficulty']) ?>
      <span class="ob-skill"><?= e($b['skill']) ?></span>
    </div>
    <h3 style="margin-top:0">About this task</h3>
    <p style="line-height:1.7"><?= e($b['description']) ?></p>
    <h3>Acceptance criteria</h3>
    <ul style="line-height:1.9;margin:0;padding-left:20px">
      <?php foreach ($b['requirements'] as $req): ?><li><?= e($req) ?></li><?php endforeach; ?>
    </ul>
    <div class="ob-note" style="margin-top:18px"><div><strong>Submissions are disabled for demo bounties.</strong> Sign in and pick a real open bounty to submit work — demo listings exist only to illustrate how a complete bounty reads.</div></div>
  </section>
  <aside class="ob-stack">
    <section class="ob-card">
      <div class="ob-reward" style="font-size:26px"><?= e(xlm($b['reward_xlm'])) ?> <small>XLM</small></div>
      <div class="ob-muted" style="margin:6px 0 12px;font-size:13px">Reward on the real board is paid through the verified Paiflow payout flow after approval. This demo listing has no payment attached.</div>
      <div style="display:grid;gap:10px;font-size:13.5px">
        <div><span class="ob-muted">Skill</span><br><?= e($b['skill']) ?></div>
        <div><span class="ob-muted">Difficulty</span><br><?= e(ucfirst($b['difficulty'])) ?></div>
        <div><span class="ob-muted">Deadline</span><br><?= $b['deadline'] ? e(ob_date($b['deadline'], 'M j, Y')) : 'No deadline' ?></div>
        <div><span class="ob-muted">Posted by</span><br><?= e($b['creator']) ?> <span class="ob-muted">(fictional)</span></div>
      </div>
    </section>
    <section class="ob-card">
      <h3>What would happen next?</h3>
      <p class="ob-muted" style="margin:4px 0 0;font-size:13.5px;line-height:1.7">On a real bounty: you submit work with a pull request link, the creator reviews it with notes, and on approval a Paiflow payout splits the reward 90% to the contributor and 10% to the community treasury — then DevOrbit verifies the on-chain evidence before anything is marked paid.</p>
      <div style="display:flex;gap:8px;margin-top:14px;flex-wrap:wrap">
        <a class="ob-btn ghost sm" href="/OpenBounty/bounties/">Browse real bounties</a>
        <a class="ob-btn ghost sm" href="/OpenBounty/skillmentor/">See demo recommendations</a>
      </div>
    </section>
  </aside>
</div>
<?php require __DIR__.'/../includes/footer.php'; ?>
