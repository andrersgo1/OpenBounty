<?php
require __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/demo-data.php';
$u = current_user();
$metric = (($_GET['metric'] ?? '') === 'earned') ? 'earned' : 'completed';
$win = in_array($_GET['window'] ?? '', ['30','90'], true) ? (int)$_GET['window'] : 0;
$skillF = (isset($_GET['skill']) && ctype_digit((string)$_GET['skill'])) ? (int)$_GET['skill'] : 0;
$hasProf = ob_table_exists('user_profiles'); $rows = []; $skills = [];
try {
  $on = "s.contributor_id=u.id AND s.status='approved'"; $args = [];
  if ($win) $on .= " AND s.reviewed_at >= DATE_SUB(NOW(), INTERVAL $win DAY)";
  $bon = 'b.id=s.bounty_id'; if ($skillF) { $bon .= ' AND b.skill_id=?'; $args[] = $skillF; }
  $pub = $hasProf ? '(up.is_public IS NULL OR up.is_public=1)' : '1=1';
  $order = $metric === 'earned' ? 'earned DESC, completed DESC' : 'completed DESC, earned DESC';
  $sql = "SELECT u.id,u.full_name,COUNT(DISTINCT s.id) completed,COALESCE(SUM(p.amount_xlm),0) earned FROM users u ".($hasProf ? 'LEFT JOIN user_profiles up ON up.user_id=u.id ' : '')."JOIN submissions s ON $on JOIN bounties b ON $bon LEFT JOIN payments p ON p.submission_id=s.id AND p.status='verified' WHERE $pub GROUP BY u.id,u.full_name HAVING ".($metric === 'earned' ? 'earned>0' : 'completed>0')." ORDER BY $order, u.full_name LIMIT 50";
  $q = db()->prepare($sql); $q->execute($args); $rows = $q->fetchAll();
  $skills = db()->query('SELECT id,name FROM skills ORDER BY name LIMIT 40')->fetchAll();
} catch (Throwable $e) { error_log($e->getMessage()); }
$link = function (array $o) use ($metric, $win, $skillF) { $p = array_filter(array_merge(['metric'=>$metric === 'completed' ? '' : $metric,'window'=>$win ?: '','skill'=>$skillF ?: ''], $o), fn($v) => $v !== '' && $v !== null); return '?'.http_build_query($p); };
$pageTitle = 'Leaderboard'; require __DIR__.'/../includes/header.php';
echo ob_page_head('Leaderboard', 'Ranked by approved work or verified payments. Only public profiles appear, and nothing is estimated.');
?>
<nav class="ob-tabs"><a class="<?= $metric==='completed'?'on':'' ?>" href="<?= e($link(['metric'=>''])) ?>">Completed bounties</a><a class="<?= $metric==='earned'?'on':'' ?>" href="<?= e($link(['metric'=>'earned'])) ?>">Verified earnings</a></nav>
<div class="ob-chips" style="margin-bottom:10px"><a class="ob-chip<?= !$win?' on':'' ?>" href="<?= e($link(['window'=>''])) ?>">All time</a><a class="ob-chip<?= $win===30?' on':'' ?>" href="<?= e($link(['window'=>'30'])) ?>">Last 30 days</a><a class="ob-chip<?= $win===90?' on':'' ?>" href="<?= e($link(['window'=>'90'])) ?>">Last 90 days</a></div>
<?php if ($skills): ?><div class="ob-chips" style="margin-bottom:18px"><a class="ob-chip<?= !$skillF?' on':'' ?>" href="<?= e($link(['skill'=>''])) ?>">All skills</a><?php foreach ($skills as $sk): ?><a class="ob-chip<?= $skillF===(int)$sk['id']?' on':'' ?>" href="<?= e($link(['skill'=>(int)$sk['id']])) ?>"><?= e($sk['name']) ?></a><?php endforeach; ?></div><?php endif; ?>
<?php if (!$rows): ?><?= ob_empty('trophy', 'No rankings yet', $metric === 'earned' ? 'Rankings appear after payments are recorded and verified.' : 'Rankings appear once bounty submissions are approved.', '<a class="ob-btn" href="/OpenBounty/bounties/">Find a bounty</a>') ?>
<?php else: ?><div class="ob-card flush"><div class="ob-tw"><table class="ob-table"><thead><tr><th style="width:70px">Rank</th><th>Developer</th><th class="num">Completed</th><th class="num">Verified earnings</th></tr></thead><tbody>
<?php $rank = 0; $prev = null; foreach ($rows as $i => $r): $val = $metric === 'earned' ? (string)$r['earned'] : (string)$r['completed']; if ($val !== $prev) { $rank = $i + 1; $prev = $val; } ?>
<tr><td><span class="ob-rank <?= $rank <= 3 ? 'r'.$rank : '' ?>"><?= $rank ?></span></td><td><div style="display:flex;gap:12px;align-items:center"><span class="ob-avatar"><?= e(ob_initials($r['full_name'])) ?></span><a class="t" href="/OpenBounty/profile/?id=<?= (int)$r['id'] ?>"><?= e($r['full_name']) ?></a><?php if ($u && (int)$u['id'] === (int)$r['id']): ?><span class="ob-badge purple">You</span><?php endif; ?></div></td><td class="num"><?= (int)$r['completed'] ?></td><td class="num"><?= e(xlm($r['earned'])) ?> XLM</td></tr><?php endforeach; ?></tbody></table></div></div><?php endif; ?>

<section style="margin-top:26px" aria-label="Example leaderboard">
<?= demo_notice('The rankings below are fictional examples that show how the leaderboard reads once a community is active. These developers are not registered users and their numbers are illustrative; the real table above is computed only from approved submissions and verified payments.') ?>
<div class="ob-card-h"><h3>Example leaderboard</h3><span class="ob-badge amber">Fictional</span></div>
<div class="ob-card flush"><div class="ob-tw"><table class="ob-table"><thead><tr><th style="width:70px">Rank</th><th>Developer (example)</th><th class="num">Completed</th><th class="num">Earned (demo)</th></tr></thead><tbody>
<?php $demoRows = demo_developers(); uasort($demoRows, fn($a, $b) => $b['completed'] <=> $a['completed']); $rank = 0; foreach ($demoRows as $dk => $r): $rank++; ?>
<tr><td><span class="ob-rank <?= $rank <= 3 ? 'r'.$rank : '' ?>"><?= $rank ?></span></td><td><div style="display:flex;gap:12px;align-items:center"><span class="ob-avatar"><?= e(ob_initials($r['name'])) ?></span><a class="t" href="/OpenBounty/developers/demo-profile.php?id=<?= e($dk) ?>"><?= e($r['name']) ?></a><span class="ob-badge amber">Demo</span></div></td><td class="num"><?= (int)$r['completed'] ?></td><td class="num"><?= e(xlm($r['earned_xlm'])) ?> XLM</td></tr>
<?php endforeach; ?></tbody></table></div></div>
</section>
<?php require __DIR__.'/../includes/footer.php'; ?>
