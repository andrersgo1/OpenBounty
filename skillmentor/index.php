<?php
require __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/demo-data.php';
$u = current_user(); $uid = $u ? (int)$u['id'] : 0;
$mine = []; $matches = []; $grow = []; $beginner = []; $hasDiff = ob_col_exists('bounties', 'difficulty');
$diffSel = $hasDiff ? 'b.difficulty' : 'NULL';
try {
  if ($u) {
    $k = db()->prepare('SELECT k.id,k.name FROM user_skills us JOIN skills k ON k.id=us.skill_id WHERE us.user_id=? ORDER BY k.name'); $k->execute([$uid]); $mine = $k->fetchAll();
    if ($mine) {
      $ids = array_map(fn($x) => (int)$x['id'], $mine); $in = implode(',', array_fill(0, count($ids), '?'));
      $m = db()->prepare("SELECT b.id,b.title,b.description,b.reward_xlm,b.status,b.deadline,u.id uid,u.full_name creator,s.name skill,$diffSel difficulty FROM bounties b JOIN users u ON u.id=b.creator_id JOIN skills s ON s.id=b.skill_id WHERE b.status='open' AND b.creator_id<>? AND b.skill_id IN ($in) ORDER BY b.created_at DESC LIMIT 6");
      $m->execute(array_merge([$uid], $ids)); $matches = $m->fetchAll();
    }
  }
  // Skills with the most open bounties that the visitor does not list yet.
  $have = $mine ? implode(',', array_map(fn($x) => (int)$x['id'], $mine)) : '0';
  $grow = db()->query("SELECT s.id,s.name,COUNT(*) n,COALESCE(SUM(b.reward_xlm),0) r FROM bounties b JOIN skills s ON s.id=b.skill_id WHERE b.status='open' AND s.id NOT IN ($have) GROUP BY s.id,s.name ORDER BY n DESC, r DESC LIMIT 6")->fetchAll();
  if ($hasDiff) $beginner = db()->query("SELECT b.id,b.title,b.description,b.reward_xlm,b.status,b.deadline,u.id uid,u.full_name creator,s.name skill,b.difficulty FROM bounties b JOIN users u ON u.id=b.creator_id LEFT JOIN skills s ON s.id=b.skill_id WHERE b.status='open' AND b.difficulty='beginner' ORDER BY b.created_at DESC LIMIT 3")->fetchAll();
} catch (Throwable $e) { error_log($e->getMessage()); }
$pageTitle = 'SkillMentor'; require __DIR__.'/../includes/header.php';
echo ob_page_head('SkillMentor', 'Learn, practise and prove skills through real bounties.', $u ? '<a class="ob-btn ghost" href="/OpenBounty/settings/#skills">Edit my skills</a>' : '<button class="ob-btn" type="button" data-open-auth="register">Join to personalize</button>');
?>
<div class="ob-steps" style="margin-bottom:18px"><div><span>01</span><h3>Choose skills</h3><p class="ob-muted" style="margin:4px 0 0">List what you know or want to practise.</p></div><div><span>02</span><h3>Find matching bounties</h3><p class="ob-muted" style="margin:4px 0 0">Recommendations come from open bounties tagged with your skills.</p></div><div><span>03</span><h3>Build proof</h3><p class="ob-muted" style="margin:4px 0 0">Approved submissions become part of your public portfolio.</p></div></div>
<?php if ($u): ?>
<section style="margin-bottom:22px"><div class="ob-card-h"><h3>Recommended for you</h3><?php if ($mine): ?><div class="ob-tags"><?php foreach ($mine as $m): ?><span class="ob-skill"><?= e($m['name']) ?></span><?php endforeach; ?></div><?php endif; ?></div>
<?php if (!$mine): ?><?= ob_empty('spark', 'Add your skills to get recommendations', 'Pick the languages and tools you work with and matching open bounties will show up here.', '<a class="ob-btn" href="/OpenBounty/settings/#skills">Choose skills</a>') ?>
<?php elseif (!$matches): ?><?= ob_empty('compass', 'No open bounties match your skills right now', 'Check back soon, or browse the full board.', '<a class="ob-btn ghost" href="/OpenBounty/bounties/">Explore bounties</a>') ?>
<?php else: ?><div class="ob-bounties"><?php foreach ($matches as $b) echo ob_bounty_card($b); ?></div><?php endif; ?></section><?php endif; ?>
<div class="ob-split"><section class="ob-card"><h3><?= $u ? 'Skills worth growing' : 'In-demand skills' ?></h3><p class="ob-muted" style="margin:4px 0 12px">Skills with the most open bounties<?= $u ? ' that are not on your list yet' : '' ?>.</p>
<?php if (!$grow): ?><p class="ob-muted" style="margin:0">No open bounties are tagged with a skill yet.</p><?php else: $mx = max(array_map(fn($x) => (int)$x['n'], $grow)); foreach ($grow as $g): ?><div class="ob-hbar"><a href="/OpenBounty/bounties/?skill=<?= (int)$g['id'] ?>"><?= e($g['name']) ?></a><div><i style="width:<?= round($g['n'] / $mx * 100) ?>%"></i></div><em><?= (int)$g['n'] ?></em></div><?php endforeach; endif; ?></section>
<aside class="ob-stack"><?php if ($beginner): ?><section class="ob-card"><h3>Beginner friendly</h3><ul class="ob-feed"><?php foreach ($beginner as $b): ?><li><span class="dot"></span><div><a href="/OpenBounty/bounties/view.php?id=<?= (int)$b['id'] ?>"><?= e($b['title']) ?></a><br><small><?= e(xlm($b['reward_xlm'])) ?> XLM · <?= e($b['skill'] ?? 'General') ?></small></div></li><?php endforeach; ?></ul></section><?php endif; ?>
<section class="ob-card"><span class="ob-badge amber">Requires future integration</span><h3 style="margin-top:10px">Mentors &amp; sessions</h3><p class="ob-muted" style="margin:4px 0 0">The database has tables for mentors, sessions and learning projects, but booking and mentor profiles are not built yet. Nothing here is simulated.</p></section></aside></div>

<section style="margin-top:26px" aria-label="Example skill-based recommendations">
<?= demo_notice('The recommendations below are worked examples: fictional developers matched to demo bounties, with the reasoning shown. They illustrate how skill-based matching reads once a community is active. Your own real recommendations above are computed only from your saved skills and real open bounties — demo content never modifies your profile.') ?>
<div class="ob-card-h"><h3>Example recommendations</h3><span class="ob-badge amber">Demo</span></div>
<div class="ob-grid" style="grid-template-columns:repeat(auto-fill,minmax(300px,1fr))">
<?php foreach (demo_recommendations() as $rec):
  $dev = demo_developer($rec['developer']); $bnt = demo_bounty($rec['bounty']);
  if (!$dev || !$bnt) continue; ?>
<article class="ob-card">
  <div class="ob-tags" style="margin-bottom:10px"><span class="ob-skill"><?= e($bnt['skill']) ?></span><?= ob_diff($bnt['difficulty']) ?></div>
  <h3 style="margin:0 0 6px;font-size:16px"><a href="/OpenBounty/bounties/demo-view.php?id=<?= e($rec['bounty']) ?>"><?= e($bnt['title']) ?></a></h3>
  <div class="ob-reward" style="margin-bottom:10px"><?= e(xlm($bnt['reward_xlm'])) ?> <small>XLM</small></div>
  <p class="ob-muted" style="margin:0;font-size:13px;line-height:1.6"><strong>Example for <?= e($dev['name']) ?>:</strong> <?= e($rec['why']) ?></p>
  <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap">
    <a class="ob-btn ghost sm" href="/OpenBounty/bounties/demo-view.php?id=<?= e($rec['bounty']) ?>">View demo bounty</a>
    <a class="ob-btn ghost sm" href="/OpenBounty/developers/demo-profile.php?id=<?= e($rec['developer']) ?>">View example developer</a>
  </div>
</article>
<?php endforeach; ?>
</div>
</section>
<?php require __DIR__.'/../includes/footer.php'; ?>
