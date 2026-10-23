<?php
require __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/demo-data.php';
$u = current_user();
$q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100);
$skillF = (isset($_GET['skill']) && ctype_digit((string)$_GET['skill'])) ? (int)$_GET['skill'] : 0;
$sort = in_array($_GET['sort'] ?? '', ['earned','newest','name'], true) ? $_GET['sort'] : 'completed';
$page = ob_page_param(); $per = 12; $rows = []; $total = 0; $skills = []; $uskills = [];
$hasProf = ob_table_exists('user_profiles');
$sel = 'u.id,u.full_name,u.bio,u.created_at,'.($hasProf ? 'up.headline,up.github_url' : 'NULL headline,NULL github_url').",
 (SELECT COUNT(*) FROM submissions s WHERE s.contributor_id=u.id AND s.status='approved') done,
 (SELECT COALESCE(SUM(p.amount_xlm),0) FROM payments p JOIN submissions z ON z.id=p.submission_id WHERE z.contributor_id=u.id AND p.status='verified') earned";
$from = 'FROM users u '.($hasProf ? 'LEFT JOIN user_profiles up ON up.user_id=u.id ' : '').'WHERE '.($hasProf ? '(up.is_public IS NULL OR up.is_public=1)' : '1=1');
$args = [];
if ($q !== '') { $l = '%'.addcslashes($q, '%_\\').'%'; $from .= ' AND (u.full_name LIKE ? OR u.bio LIKE ? OR EXISTS(SELECT 1 FROM user_skills x JOIN skills k ON k.id=x.skill_id WHERE x.user_id=u.id AND k.name LIKE ?))'; array_push($args, $l, $l, $l); }
if ($skillF) { $from .= ' AND EXISTS(SELECT 1 FROM user_skills x WHERE x.user_id=u.id AND x.skill_id=?)'; $args[] = $skillF; }
$order = ['completed'=>'done DESC, earned DESC, u.full_name','earned'=>'earned DESC, done DESC, u.full_name','newest'=>'u.created_at DESC','name'=>'u.full_name ASC'][$sort];
try {
  $c = db()->prepare("SELECT COUNT(*) $from"); $c->execute($args); $total = (int)$c->fetchColumn(); $off = ($page - 1) * $per;
  $r = db()->prepare("SELECT $sel $from ORDER BY $order LIMIT $per OFFSET $off"); $r->execute($args); $rows = $r->fetchAll();
  $skills = db()->query('SELECT id,name FROM skills ORDER BY name LIMIT 40')->fetchAll();
  if ($rows) { $ids = array_map(fn($x) => (int)$x['id'], $rows); $in = implode(',', array_fill(0, count($ids), '?')); $k = db()->prepare("SELECT us.user_id,k.name FROM user_skills us JOIN skills k ON k.id=us.skill_id WHERE us.user_id IN ($in) ORDER BY k.name"); $k->execute($ids); foreach ($k->fetchAll() as $x) $uskills[(int)$x['user_id']][] = $x['name']; }
} catch (Throwable $e) { error_log($e->getMessage()); }
$params = ['q'=>$q,'skill'=>$skillF ?: '','sort'=>$sort === 'completed' ? '' : $sort];
$link = function (array $o) use ($params) { $p = array_filter(array_merge($params, $o), fn($v) => $v !== '' && $v !== null); return '?'.http_build_query($p); };
$pageTitle = 'Developers'; require __DIR__.'/../includes/header.php';
echo ob_page_head('Developers', 'Find contributors by skill, track record and verified work.');
?>
<form class="ob-filters" method="get"><div class="ob-gsearch grow" style="max-width:none"><?= ob_icon('search', 16) ?><input type="search" name="q" value="<?= e($q) ?>" placeholder="Search by name, bio or skill" aria-label="Search developers" maxlength="100"></div><?php if ($skillF): ?><input type="hidden" name="skill" value="<?= $skillF ?>"><?php endif; ?>
<select class="ob-in" name="sort" data-autosubmit aria-label="Sort"><option value="completed"<?= $sort==='completed'?' selected':'' ?>>Most completed</option><option value="earned"<?= $sort==='earned'?' selected':'' ?>>Highest verified earnings</option><option value="newest"<?= $sort==='newest'?' selected':'' ?>>Newest members</option><option value="name"<?= $sort==='name'?' selected':'' ?>>Name A–Z</option></select><button class="ob-btn ghost" type="submit">Search</button></form>
<?php if ($skills): ?><div class="ob-chips" style="margin-bottom:18px"><a class="ob-chip<?= !$skillF?' on':'' ?>" href="<?= e($link(['skill'=>''])) ?>">All skills</a><?php foreach ($skills as $sk): ?><a class="ob-chip<?= $skillF===(int)$sk['id']?' on':'' ?>" href="<?= e($link(['skill'=>(int)$sk['id']])) ?>"><?= e($sk['name']) ?></a><?php endforeach; ?></div><?php endif; ?>
<?php if (!$rows): ?><?= ob_empty('users', 'No developers found', ($q || $skillF) ? 'Try a different search or clear the skill filter.' : 'Developers appear here once they create an account.', ($q || $skillF) ? '<a class="ob-btn ghost" href="/OpenBounty/developers/">Clear filters</a>' : '') ?>
<?php else: ?><div class="ob-grid" style="grid-template-columns:repeat(auto-fill,minmax(280px,1fr))"><?php foreach ($rows as $d): $gh = ob_safe_url($d['github_url']); ?>
<article class="ob-card ob-dev"><div style="display:flex;gap:12px;align-items:center"><span class="ob-avatar md"><?= e(ob_initials($d['full_name'])) ?></span><div><h3 style="margin:0"><a href="/OpenBounty/profile/?id=<?= (int)$d['id'] ?>"><?= e($d['full_name']) ?></a></h3><div class="ob-muted" style="font-size:12.5px"><?= $d['headline'] ? e($d['headline']) : 'DevOrbit member' ?></div></div></div>
<div class="ob-tags"><?php foreach (array_slice($uskills[(int)$d['id']] ?? [], 0, 5) as $sn): ?><span class="ob-skill"><?= e($sn) ?></span><?php endforeach; if (empty($uskills[(int)$d['id']])): ?><span class="ob-muted" style="font-size:12.5px">No skills listed</span><?php endif; ?></div>
<div class="ob-grid ob-g2" style="width:100%;gap:8px"><div><span class="ob-muted" style="font-size:11.5px">Completed</span><div style="font-weight:700;font-size:18px"><?= (int)$d['done'] ?></div></div><div><span class="ob-muted" style="font-size:11.5px">Verified earnings</span><div style="font-weight:700;font-size:18px"><?= e(xlm($d['earned'])) ?> <small class="ob-muted">XLM</small></div></div></div>
<div style="display:flex;gap:8px"><a class="ob-btn ghost sm" href="/OpenBounty/profile/?id=<?= (int)$d['id'] ?>">View profile</a><?php if ($gh): ?><a class="ob-btn ghost sm" href="<?= e($gh) ?>" target="_blank" rel="noopener noreferrer nofollow"><?= ob_icon('link', 14) ?>GitHub</a><?php endif; ?></div></article><?php endforeach; ?></div><?= ob_pager($total, $per, $page, $params) ?><?php endif; ?>

<section style="margin-top:26px" aria-label="Example developer profiles">
<?= demo_notice('The profiles below are fictional examples that show what a mature developer profile looks like — skills, portfolio and reputation built from verified payouts. They are not registered accounts, do not appear in real search rankings, and their statistics are illustrative only.') ?>
<div class="ob-card-h"><h3>Example developer profiles</h3><span class="ob-badge amber">Fictional</span></div>
<div class="ob-grid" style="grid-template-columns:repeat(auto-fill,minmax(280px,1fr))">
<?php foreach (demo_developers() as $dk => $d): ?>
<article class="ob-card ob-dev">
  <div style="display:flex;gap:12px;align-items:center"><span class="ob-avatar md"><?= e(ob_initials($d['name'])) ?></span><div><h3 style="margin:0"><a href="/OpenBounty/developers/demo-profile.php?id=<?= e($dk) ?>"><?= e($d['name']) ?></a></h3><div class="ob-muted" style="font-size:12.5px"><?= e($d['headline']) ?></div></div></div>
  <div class="ob-tags"><?php foreach ($d['skills'] as $sn): ?><span class="ob-skill"><?= e($sn) ?></span><?php endforeach; ?></div>
  <div class="ob-grid ob-g2" style="width:100%;gap:8px"><div><span class="ob-muted" style="font-size:11.5px">Completed (demo)</span><div style="font-weight:700;font-size:18px"><?= (int)$d['completed'] ?></div></div><div><span class="ob-muted" style="font-size:11.5px">Earned (demo)</span><div style="font-weight:700;font-size:18px"><?= e(xlm($d['earned_xlm'])) ?> <small class="ob-muted">XLM</small></div></div></div>
  <div><a class="ob-btn ghost sm" href="/OpenBounty/developers/demo-profile.php?id=<?= e($dk) ?>">View example profile</a></div>
</article>
<?php endforeach; ?>
</div>
</section>
<?php require __DIR__.'/../includes/footer.php'; ?>
