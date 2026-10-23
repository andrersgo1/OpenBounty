<?php
require __DIR__.'/../includes/bootstrap.php'; require_login(); $u = current_user();
$tabs = ['all'=>'All','draft'=>'Drafts','open'=>'Open','in_review'=>'In review','done'=>'Approved / paid','closed'=>'Closed'];
$tab = array_key_exists($_GET['tab'] ?? '', $tabs) ? $_GET['tab'] : 'all';
$page = ob_page_param(); $per = 15; $rows = []; $total = 0; $counts = array_fill_keys(array_keys($tabs), 0);
$cond = ['all'=>'1=1','draft'=>"b.status='draft'",'open'=>"b.status='open'",'in_review'=>"b.status='in_review'",'done'=>"b.status IN ('approved','payment_pending','paid')",'closed'=>"b.status='closed'"];
try {
  $s = db()->prepare("SELECT COUNT(*) all_n, COALESCE(SUM(status='draft'),0) draft_n, COALESCE(SUM(status='open'),0) open_n, COALESCE(SUM(status='in_review'),0) rev_n, COALESCE(SUM(status IN ('approved','payment_pending','paid')),0) done_n, COALESCE(SUM(status='closed'),0) closed_n FROM bounties WHERE creator_id=?"); $s->execute([$u['id']]); $c = $s->fetch();
  $counts = ['all'=>(int)$c['all_n'],'draft'=>(int)$c['draft_n'],'open'=>(int)$c['open_n'],'in_review'=>(int)$c['rev_n'],'done'=>(int)$c['done_n'],'closed'=>(int)$c['closed_n']];
  $total = $counts[$tab]; $off = ($page - 1) * $per;
  $s = db()->prepare("SELECT b.id,b.title,b.status,b.reward_xlm,b.deadline,b.created_at,sk.name skill,(SELECT COUNT(*) FROM submissions x WHERE x.bounty_id=b.id) subs,(SELECT COUNT(*) FROM submissions x WHERE x.bounty_id=b.id AND x.status IN ('submitted','in_review','changes_requested')) pending,(SELECT COUNT(*) FROM submissions x WHERE x.bounty_id=b.id AND x.status='approved') approved FROM bounties b LEFT JOIN skills sk ON sk.id=b.skill_id WHERE b.creator_id=? AND {$cond[$tab]} ORDER BY b.created_at DESC LIMIT $per OFFSET $off");
  $s->execute([$u['id']]); $rows = $s->fetchAll();
} catch (Throwable $e) { error_log($e->getMessage()); }
$pageTitle = 'My bounties'; require __DIR__.'/../includes/header.php';
echo ob_page_head('My bounties', 'Bounties you created: manage status, rewards and incoming submissions.', '<a class="ob-btn" href="/OpenBounty/bounties/create.php">'.ob_icon('plus', 16).'Create bounty</a>');
?>
<nav class="ob-tabs" aria-label="Filter by status"><?php foreach ($tabs as $k => $label): ?><a class="<?= $tab === $k ? 'on' : '' ?>" href="?tab=<?= e($k) ?>"><?= e($label) ?> <span class="ob-muted">(<?= (int)$counts[$k] ?>)</span></a><?php endforeach; ?></nav>
<?php if (!$rows): ?>
  <?= $counts['all'] === 0 ? ob_empty('flag', 'You have not posted a bounty yet', 'Create your first bounty and let contributors help you build it.', '<a class="ob-btn" href="/OpenBounty/bounties/create.php">Create a bounty</a>') : ob_empty('inbox', 'Nothing in this view', 'No bounties match this status.', '<a class="ob-btn ghost" href="?tab=all">Show all</a>') ?>
<?php else: ?>
<div class="ob-card flush"><div class="ob-tw"><table class="ob-table"><thead><tr><th>Bounty</th><th>Status</th><th class="num">Reward</th><th>Deadline</th><th class="num">Submissions</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($rows as $r): $locked = in_array($r['status'], ['approved','payment_pending','paid'], true); ?>
<tr><td><a class="t" href="/OpenBounty/bounties/view.php?id=<?= (int)$r['id'] ?>"><?= e($r['title']) ?></a><div class="ob-muted" style="font-size:12px"><?= e($r['skill'] ?? 'General') ?> · created <?= e(ob_date($r['created_at'])) ?></div></td>
<td><?= ob_badge((string)$r['status']) ?></td><td class="num"><strong><?= e(xlm($r['reward_xlm'])) ?></strong> <small class="ob-muted">XLM</small></td><td><?= $r['deadline'] ? e(ob_date($r['deadline'])).'<div style="font-size:12px">'.ob_deadline($r['deadline']).'</div>' : '<span class="ob-muted">—</span>' ?></td>
<td class="num"><?= (int)$r['subs'] ?><?php if ((int)$r['pending'] > 0): ?> <span class="ob-badge amber"><?= (int)$r['pending'] ?> to review</span><?php endif; ?></td>
<td><div style="display:flex;gap:6px;flex-wrap:wrap"><a class="ob-btn ghost sm" href="/OpenBounty/bounties/view.php?id=<?= (int)$r['id'] ?>">Manage</a><?php if (!$locked): ?><a class="ob-btn ghost sm" href="/OpenBounty/bounties/edit.php?id=<?= (int)$r['id'] ?>">Edit</a><?php endif; ?>
<?php if (in_array($r['status'], ['open','draft','in_review'], true) && (int)$r['approved'] === 0): ?><form method="post" action="/OpenBounty/bounties/manage.php"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="bounty_id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="return" value="/OpenBounty/bounties/mine.php?tab=<?= e($tab) ?>"><button class="ob-btn danger sm" name="action" value="close" type="submit" data-confirm="Close this bounty?">Close</button></form><?php endif; ?></div></td></tr>
<?php endforeach; ?></tbody></table></div></div>
<?= ob_pager($total, $per, $page, ['tab'=>$tab]) ?>
<?php endif; ?>
<?php require __DIR__.'/../includes/footer.php'; ?>
