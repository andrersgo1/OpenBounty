<?php
require __DIR__.'/../includes/bootstrap.php'; require_login(); $u = current_user(); $uid = (int)$u['id'];
$one = function (string $sql, array $a = []) { $s = db()->prepare($sql); $s->execute($a); return $s->fetchColumn(); };
$st = ['my_bounties'=>0,'my_subs'=>0]; $dev = []; $cr = []; $monthDev = []; $monthCr = []; $byStatus = []; $payStats = []; $global = ['open'=>0,'rewards'=>'0']; $err = false;
try {
  $st['my_bounties'] = (int)$one('SELECT COUNT(*) FROM bounties WHERE creator_id=?', [$uid]);
  $st['my_subs'] = (int)$one('SELECT COUNT(*) FROM submissions WHERE contributor_id=?', [$uid]);
  $g = db()->query("SELECT COUNT(*) n, COALESCE(SUM(reward_xlm),0) r FROM bounties WHERE status='open'")->fetch(); $global = ['open'=>(int)$g['n'],'rewards'=>(string)$g['r']];
  $dev = [
    'active'=>(int)$one("SELECT COUNT(*) FROM submissions WHERE contributor_id=? AND status IN ('submitted','in_review','changes_requested')", [$uid]),
    'completed'=>(int)$one("SELECT COUNT(*) FROM submissions WHERE contributor_id=? AND status='approved'", [$uid]),
    'earned'=>(string)$one("SELECT COALESCE(SUM(p.amount_xlm),0) FROM payments p JOIN submissions s ON s.id=p.submission_id WHERE s.contributor_id=? AND p.status='verified'", [$uid]),
    'pending'=>(string)$one("SELECT COALESCE(SUM(b.reward_xlm),0) FROM submissions s JOIN bounties b ON b.id=s.bounty_id LEFT JOIN payments p ON p.submission_id=s.id WHERE s.contributor_id=? AND s.status='approved' AND (p.id IS NULL OR p.status<>'verified')", [$uid]),
  ];
  $cr = [
    'active'=>(int)$one("SELECT COUNT(*) FROM bounties WHERE creator_id=? AND status IN ('open','in_review')", [$uid]),
    'committed'=>(string)$one("SELECT COALESCE(SUM(reward_xlm),0) FROM bounties WHERE creator_id=? AND status IN ('open','in_review','approved','payment_pending')", [$uid]),
    'to_review'=>(int)$one("SELECT COUNT(*) FROM submissions s JOIN bounties b ON b.id=s.bounty_id WHERE b.creator_id=? AND s.status IN ('submitted','in_review')", [$uid]),
    'completed'=>(int)$one("SELECT COUNT(*) FROM bounties WHERE creator_id=? AND status IN ('approved','payment_pending','paid')", [$uid]),
    'paid'=>(string)$one("SELECT COALESCE(SUM(amount_xlm),0) FROM payments WHERE payer_id=? AND status='verified'", [$uid]),
  ];
  $q = db()->prepare("SELECT status, COUNT(*) n FROM bounties WHERE creator_id=? GROUP BY status"); $q->execute([$uid]); $byStatus = array_column($q->fetchAll(), 'n', 'status');
  $q = db()->prepare("SELECT status, COUNT(*) n FROM payments WHERE payer_id=? GROUP BY status"); $q->execute([$uid]); $payStats = array_column($q->fetchAll(), 'n', 'status');
  $q = db()->prepare("SELECT DATE_FORMAT(created_at,'%Y-%m') m, COUNT(*) n FROM submissions WHERE contributor_id=? AND created_at>=DATE_SUB(DATE_FORMAT(NOW(),'%Y-%m-01'), INTERVAL 5 MONTH) GROUP BY m"); $q->execute([$uid]); $monthDev = array_column($q->fetchAll(), 'n', 'm');
  $q = db()->prepare("SELECT DATE_FORMAT(s.created_at,'%Y-%m') m, COUNT(*) n FROM submissions s JOIN bounties b ON b.id=s.bounty_id WHERE b.creator_id=? AND s.created_at>=DATE_SUB(DATE_FORMAT(NOW(),'%Y-%m-01'), INTERVAL 5 MONTH) GROUP BY m"); $q->execute([$uid]); $monthCr = array_column($q->fetchAll(), 'n', 'm');
  $rec = db()->prepare("SELECT b.id,b.title,b.reward_xlm,b.status,b.deadline,u.id uid,u.full_name creator,s.name skill,".(ob_col_exists('bounties','difficulty') ? 'b.difficulty' : 'NULL')." difficulty FROM bounties b JOIN users u ON u.id=b.creator_id LEFT JOIN skills s ON s.id=b.skill_id WHERE b.status='open' AND b.creator_id<>? ORDER BY b.created_at DESC LIMIT 3"); $rec->execute([$uid]); $recent = $rec->fetchAll();
} catch (Throwable $e) { error_log($e->getMessage()); $err = true; $recent = []; }
$view = $_GET['view'] ?? ''; if (!in_array($view, ['developer','creator'], true)) $view = ($st['my_bounties'] > 0 && $st['my_subs'] === 0) ? 'creator' : 'developer';
$months = []; for ($i = 5; $i >= 0; $i--) { $t = strtotime("first day of -$i month"); $months[date('Y-m', $t)] = date('M', $t); }
$series = $view === 'creator' ? $monthCr : $monthDev; $max = max(1, ...array_map(fn($k) => (int)($series[$k] ?? 0), array_keys($months)));
$activity = ob_notifications($uid, 6);
$pageTitle = 'Dashboard'; require __DIR__.'/../includes/header.php';
echo ob_page_head('Welcome back, '.explode(' ', $u['full_name'])[0], 'Your workspace at a glance, built from your real activity.', '<a class="ob-btn" href="/OpenBounty/bounties/create.php">'.ob_icon('plus', 16).'Post a bounty</a>');
if ($err): ?><div class="ob-flash error">Some dashboard data could not be loaded. Confirm MySQL is running and the schema has been imported.</div><?php endif; ?>
<nav class="ob-tabs"><a class="<?= $view==='developer'?'on':'' ?>" href="?view=developer">As a developer</a><a class="<?= $view==='creator'?'on':'' ?>" href="?view=creator">As a bounty creator</a></nav>
<?php
$cards = $view === 'creator'
 ? [['flag','Active bounties',$cr['active'] ?? 0,'Open or in review'],['wallet','Rewards committed',xlm($cr['committed'] ?? 0).' XLM','Across unfinished bounties'],['check','Pending reviews',$cr['to_review'] ?? 0,'Submissions awaiting you'],['trophy','Completed',$cr['completed'] ?? 0,'Approved or paid'],['card','Verified payouts',xlm($cr['paid'] ?? 0).' XLM','Confirmed on Testnet']]
 : [['upload','Pending submissions',$dev['active'] ?? 0,'In review or needs changes'],['trophy','Completed tasks',$dev['completed'] ?? 0,'Approved work'],['wallet','Verified earnings',xlm($dev['earned'] ?? 0).' XLM','Only verified payments count'],['clock','Pending rewards',xlm($dev['pending'] ?? 0).' XLM','Approved, not yet verified paid'],['compass','Open bounties',$global['open'],xlm($global['rewards']).' XLM available']];
?>
<div class="ob-grid" style="grid-template-columns:repeat(auto-fit,minmax(190px,1fr));margin-bottom:18px"><?php foreach ($cards as [$ic, $lb, $val, $sub]): ?><div class="ob-card ob-stat"><div class="ob-ico"><?= ob_icon($ic) ?></div><span><?= e($lb) ?></span><strong><?= e((string)$val) ?></strong><small><?= e($sub) ?></small></div><?php endforeach; ?></div>
<div class="ob-split"><div class="ob-stack">
  <section class="ob-card"><div class="ob-card-h"><h3><?= $view==='creator' ? 'Submissions received' : 'Your submissions' ?> · last 6 months</h3></div>
    <div class="ob-bars" role="img" aria-label="Monthly submissions chart"><?php foreach ($months as $k => $lb): $n = (int)($series[$k] ?? 0); ?><div class="ob-bar"><b><?= $n ?></b><i style="height:<?= max(2, round($n / $max * 100)) ?>%"></i><span><?= e($lb) ?></span></div><?php endforeach; ?></div></section>
  <?php if ($view === 'creator'): ?>
  <section class="ob-card"><div class="ob-card-h"><h3>Your bounties by status</h3></div><?php $tot = max(1, array_sum($byStatus)); if (!$byStatus): ?><p class="ob-muted">No bounties yet.</p><?php endif; foreach ($byStatus as $k => $n): ?><div class="ob-hbar"><span><?= e(ob_label((string)$k)) ?></span><div><i style="width:<?= round($n / $tot * 100) ?>%"></i></div><em><?= (int)$n ?></em></div><?php endforeach; ?></section>
  <section class="ob-card"><div class="ob-card-h"><h3>Payment statistics</h3><a href="/OpenBounty/transactions/">All transactions</a></div><?php if (!$payStats): ?><p class="ob-muted" style="margin:0">No payments recorded yet. Payments appear here only after they are recorded and verified.</p><?php else: ?><div style="display:flex;gap:8px;flex-wrap:wrap"><?php foreach ($payStats as $k => $n): ?><span><?= ob_badge((string)$k) ?> <strong><?= (int)$n ?></strong></span><?php endforeach; ?></div><?php endif; ?></section>
  <?php else: ?>
  <section class="ob-card"><div class="ob-card-h"><h3>Open bounties for you</h3><a href="/OpenBounty/skillmentor/">Recommendations</a></div><?php if (!$recent): ?><p class="ob-muted" style="margin:0">No open bounties from other creators right now.</p><?php else: ?><div class="ob-bounties" style="grid-template-columns:1fr"><?php foreach ($recent as $b) echo ob_bounty_card($b); ?></div><?php endif; ?></section>
  <?php endif; ?>
 </div>
 <aside class="ob-stack"><section class="ob-card"><div class="ob-card-h"><h3>Recent activity</h3><a href="/OpenBounty/notifications/">View all</a></div>
  <?php if (!$activity): ?><p class="ob-muted" style="margin:0">Reviews, new submissions and payment updates will appear here.</p><?php else: ?><ul class="ob-feed"><?php foreach ($activity as $a): ?><li class="<?= $a['read'] ? 'read' : '' ?>"><span class="dot"></span><div><a href="<?= e($a['url']) ?>"><?= e($a['text']) ?></a><br><small><?= e(ob_ago($a['at'])) ?></small></div></li><?php endforeach; ?></ul><?php endif; ?></section>
  <section class="ob-card glow"><h3>Quick links</h3><div style="display:grid;gap:8px;margin-top:10px"><a class="ob-btn ghost" href="/OpenBounty/bounties/mine.php">My bounties</a><a class="ob-btn ghost" href="/OpenBounty/submissions/">My submissions</a><a class="ob-btn ghost" href="/OpenBounty/reviews/">Reviews</a><a class="ob-btn ghost" href="/OpenBounty/wallet/">Wallet</a></div></section></aside></div>
<?php require __DIR__.'/../includes/footer.php'; ?>
