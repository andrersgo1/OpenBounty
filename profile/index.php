<?php
require __DIR__.'/../includes/bootstrap.php'; $u = current_user();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { require_login(); $id = (int)$u['id']; }
$own = $u && (int)$u['id'] === (int)$id;
$hasProf = ob_table_exists('user_profiles');
$s = db()->prepare('SELECT u.id,u.full_name,u.bio,u.created_at,u.role'.($hasProf ? ',up.headline,up.github_url,up.website_url,up.is_public' : ',NULL headline,NULL github_url,NULL website_url,1 is_public').' FROM users u '.($hasProf ? 'LEFT JOIN user_profiles up ON up.user_id=u.id ' : '').'WHERE u.id=?');
$s->execute([$id]); $p = $s->fetch();
// Private profiles are visible only to their owner. Email and wallet address are never shown here.
if (!$p || (!$own && $p['is_public'] !== null && (int)$p['is_public'] === 0)) { http_response_code(404); $pageTitle = 'Profile not found'; require __DIR__.'/../includes/header.php'; echo ob_empty('user', 'Profile not found', 'This profile does not exist or is private.', '<a class="ob-btn ghost" href="/OpenBounty/developers/">Browse developers</a>'); require __DIR__.'/../includes/footer.php'; exit; }
$one = function (string $sql, array $a) { $q = db()->prepare($sql); $q->execute($a); return $q->fetchColumn(); };
$done = (int)$one("SELECT COUNT(*) FROM submissions WHERE contributor_id=? AND status='approved'", [$id]);
$earned = (string)$one("SELECT COALESCE(SUM(p.amount_xlm),0) FROM payments p JOIN submissions z ON z.id=p.submission_id WHERE z.contributor_id=? AND p.status='verified'", [$id]);
$posted = (int)$one("SELECT COUNT(*) FROM bounties WHERE creator_id=? AND status<>'draft'", [$id]);
$k = db()->prepare('SELECT k.name,us.proficiency FROM user_skills us JOIN skills k ON k.id=us.skill_id WHERE us.user_id=? ORDER BY k.name'); $k->execute([$id]); $skills = $k->fetchAll();
$h = db()->prepare("SELECT s.status,s.reviewed_at,s.created_at,b.id bid,b.title,b.reward_xlm,sk.name skill FROM submissions s JOIN bounties b ON b.id=s.bounty_id LEFT JOIN skills sk ON sk.id=b.skill_id WHERE s.contributor_id=? ".($own ? '' : "AND s.status='approved' ")."ORDER BY COALESCE(s.reviewed_at,s.created_at) DESC LIMIT 10"); $h->execute([$id]); $hist = $h->fetchAll();
$gh = ob_safe_url($p['github_url'] ?? ''); $web = ob_safe_url($p['website_url'] ?? '');
$pageTitle = $p['full_name']; require __DIR__.'/../includes/header.php';
?>
<section class="ob-card glow" style="margin-bottom:18px"><div style="display:flex;gap:20px;align-items:center;flex-wrap:wrap"><span class="ob-avatar lg"><?= e(ob_initials($p['full_name'])) ?></span><div style="flex:1;min-width:220px"><h2 style="margin:0;font-size:26px"><?= e($p['full_name']) ?></h2><div class="ob-muted"><?= $p['headline'] ? e($p['headline']) : 'DevOrbit member' ?> · joined <?= e(ob_date($p['created_at'], 'M Y')) ?></div>
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px"><?php if ($gh): ?><a class="ob-btn ghost sm" href="<?= e($gh) ?>" target="_blank" rel="noopener noreferrer nofollow"><?= ob_icon('link', 14) ?>GitHub</a><?php endif; ?><?php if ($web): ?><a class="ob-btn ghost sm" href="<?= e($web) ?>" target="_blank" rel="noopener noreferrer nofollow"><?= ob_icon('ext', 14) ?>Website</a><?php endif; ?></div></div>
<?php if ($own): ?><a class="ob-btn" href="/OpenBounty/settings/">Edit profile</a><?php endif; ?></div>
<?php if ($own && $hasProf && $p['is_public'] !== null && (int)$p['is_public'] === 0): ?><p class="ob-hint" style="margin-top:12px">Your profile is private: only you can see this page.</p><?php endif; ?></section>
<div class="ob-grid ob-g3" style="margin-bottom:18px"><div class="ob-card ob-stat"><span>Completed bounties</span><strong><?= $done ?></strong></div><div class="ob-card ob-stat"><span>Verified earnings</span><strong><?= e(xlm($earned)) ?> XLM</strong><small>Verified payments only</small></div><div class="ob-card ob-stat"><span>Bounties posted</span><strong><?= $posted ?></strong></div></div>
<div class="ob-split"><div class="ob-stack">
 <section class="ob-card"><h3>About</h3><?php if (!empty($p['bio'])): ?><div class="ob-prose" style="margin-top:8px"><?= e($p['bio']) ?></div><?php else: ?><p class="ob-muted" style="margin:8px 0 0"><?= $own ? 'Add a short bio in settings so others know what you work on.' : 'No bio yet.' ?></p><?php endif; ?></section>
 <section class="ob-card flush"><div class="ob-card-h" style="padding:18px 20px 0"><h3>Contribution history</h3></div><?php if (!$hist): ?><div style="padding:0 20px 20px"><p class="ob-muted" style="margin:0">No contributions yet.</p></div><?php else: ?><div class="ob-tw"><table class="ob-table"><tbody><?php foreach ($hist as $x): ?><tr><td><a class="t" href="/OpenBounty/bounties/view.php?id=<?= (int)$x['bid'] ?>"><?= e($x['title']) ?></a><div class="ob-muted" style="font-size:12px"><?= e($x['skill'] ?? 'General') ?> · <?= e(ob_date($x['reviewed_at'] ?: $x['created_at'])) ?></div></td><td><?= ob_badge((string)$x['status']) ?></td><td class="num"><?= e(xlm($x['reward_xlm'])) ?> XLM</td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section></div>
 <aside class="ob-stack"><section class="ob-card"><h3>Skills</h3><?php if (!$skills): ?><p class="ob-muted" style="margin:8px 0 0"><?= $own ? 'Choose your skills in settings.' : 'No skills listed.' ?></p><?php else: ?><div class="ob-tags" style="margin-top:10px"><?php foreach ($skills as $sk): ?><span class="ob-skill"><?= e($sk['name']) ?> · <?= e($sk['proficiency']) ?></span><?php endforeach; ?></div><?php endif; ?></section></aside></div>
<?php require __DIR__.'/../includes/footer.php'; ?>
