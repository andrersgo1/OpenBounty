<?php
require __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/demo-data.php';
$u = current_user();
$publicStatuses = "'open','in_review','approved','payment_pending','paid'";
$q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100);
$skillF = (isset($_GET['skill']) && ctype_digit((string)$_GET['skill'])) ? (int)$_GET['skill'] : 0;
$statusF = in_array($_GET['status'] ?? '', ['open','in_review','completed'], true) ? $_GET['status'] : '';
$diffF = in_array($_GET['difficulty'] ?? '', ['beginner','intermediate','advanced'], true) ? $_GET['difficulty'] : '';
$dlF = in_array($_GET['deadline'] ?? '', ['week','month','none'], true) ? $_GET['deadline'] : '';
$sort = in_array($_GET['sort'] ?? '', ['reward_desc','reward_asc','deadline'], true) ? $_GET['sort'] : 'newest';
$view = (($_GET['view'] ?? '') === 'list') ? 'list' : 'grid';
$page = ob_page_param(); $per = 12;
$hasDiff = ob_col_exists('bounties', 'difficulty');
if (!$hasDiff) $diffF = '';
$params = ['q'=>$q,'skill'=>$skillF ?: '','status'=>$statusF,'difficulty'=>$diffF,'deadline'=>$dlF,'sort'=>$sort === 'newest' ? '' : $sort,'view'=>$view === 'grid' ? '' : 'list'];
$link = function (array $over) use ($params) { $p = array_merge($params, $over); unset($p['page']); $p = array_filter($p, fn($v) => $v !== '' && $v !== null); return '?'.http_build_query($p); };

$where = ["b.status IN ($publicStatuses)"]; $args = [];
if ($q !== '') { $like = '%'.addcslashes($q, '%_\\').'%'; $where[] = '(b.title LIKE ? OR b.description LIKE ? OR s.name LIKE ?)'; array_push($args, $like, $like, $like); }
if ($skillF) { $where[] = 'b.skill_id = ?'; $args[] = $skillF; }
if ($statusF === 'open') $where[] = "b.status='open'";
elseif ($statusF === 'in_review') $where[] = "b.status='in_review'";
elseif ($statusF === 'completed') $where[] = "b.status IN ('approved','payment_pending','paid')";
if ($diffF) { $where[] = 'b.difficulty = ?'; $args[] = $diffF; }
if ($dlF === 'week') $where[] = 'b.deadline BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)';
elseif ($dlF === 'month') $where[] = 'b.deadline BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY)';
elseif ($dlF === 'none') $where[] = 'b.deadline IS NULL';
$order = ['reward_desc'=>'b.reward_xlm DESC, b.id DESC','reward_asc'=>'b.reward_xlm ASC, b.id DESC','deadline'=>'(b.deadline IS NULL), b.deadline ASC, b.id DESC','newest'=>"FIELD(b.status,'open','in_review','approved','payment_pending','paid'), b.created_at DESC"][$sort];
$from = 'FROM bounties b JOIN users u ON u.id=b.creator_id LEFT JOIN skills s ON s.id=b.skill_id WHERE '.implode(' AND ', $where);
$rows = []; $total = 0; $skills = []; $error = false;
try {
  $s = db()->prepare("SELECT COUNT(*) $from"); $s->execute($args); $total = (int)$s->fetchColumn();
  $off = ($page - 1) * $per;
  $s = db()->prepare('SELECT b.id,b.title,b.description,b.reward_xlm,b.status,b.deadline,b.created_at,u.id uid,u.full_name creator,s.name skill,'.($hasDiff ? 'b.difficulty' : 'NULL').' AS difficulty '.$from." ORDER BY $order LIMIT $per OFFSET $off");
  $s->execute($args); $rows = $s->fetchAll();
  $skills = db()->query('SELECT id,name FROM skills ORDER BY name LIMIT 40')->fetchAll();
} catch (Throwable $e) { error_log($e->getMessage()); $error = true; }
// Demo layer: fictional bounties filtered with the same semantics, rendered
// through the same cards with a Demo badge. Never written to the database.
$demoMatches = demo_bounty_matches(['q'=>$q,'status'=>$statusF,'skill'=>$skillF,'difficulty'=>$diffF,'deadline'=>$dlF]);
if ($demoMatches && !$error) {
  $total += count($demoMatches);
  if ($page === 1) {
    $combined = array_merge($rows, $demoMatches);
    usort($combined, demo_bounty_compare($sort));
    $rows = array_slice($combined, 0, $per);
  }
}
$filtered = ($q !== '' || $skillF || $statusF || $diffF || $dlF);
$pageTitle = 'Explore bounties'; require __DIR__.'/../includes/header.php';
$postAttr = $u ? '' : ' data-auth-required="1" data-auth-mode="register"';
?>
<?= ob_page_head('Explore bounties', 'Find a real task worth solving. Filter by language, reward, difficulty and deadline.', '<a class="ob-btn" href="/OpenBounty/bounties/create.php"'.$postAttr.'>'.ob_icon('plus', 16).'Post a bounty</a>') ?>
<?php if ($error): ?><div class="ob-flash error">Could not load bounties. Confirm MySQL is running and the schema has been imported.</div><?php endif; ?>

<form class="ob-filters" method="get" action="/OpenBounty/bounties/">
  <div class="ob-gsearch grow" style="max-width:none"><?= ob_icon('search', 16) ?><input type="search" name="q" value="<?= e($q) ?>" placeholder="Search title, description or skill" aria-label="Search bounties" maxlength="100"></div>
  <?php if ($skillF): ?><input type="hidden" name="skill" value="<?= $skillF ?>"><?php endif; ?>
  <select class="ob-in" name="status" data-autosubmit aria-label="Status"><option value="">Any status</option><option value="open"<?= $statusF==='open'?' selected':'' ?>>Open</option><option value="in_review"<?= $statusF==='in_review'?' selected':'' ?>>In review</option><option value="completed"<?= $statusF==='completed'?' selected':'' ?>>Completed</option></select>
  <?php if ($hasDiff): ?><select class="ob-in" name="difficulty" data-autosubmit aria-label="Difficulty"><option value="">Any difficulty</option><option value="beginner"<?= $diffF==='beginner'?' selected':'' ?>>Beginner friendly</option><option value="intermediate"<?= $diffF==='intermediate'?' selected':'' ?>>Intermediate</option><option value="advanced"<?= $diffF==='advanced'?' selected':'' ?>>Advanced</option></select><?php endif; ?>
  <select class="ob-in" name="deadline" data-autosubmit aria-label="Deadline"><option value="">Any deadline</option><option value="week"<?= $dlF==='week'?' selected':'' ?>>Due within 7 days</option><option value="month"<?= $dlF==='month'?' selected':'' ?>>Due within 30 days</option><option value="none"<?= $dlF==='none'?' selected':'' ?>>No deadline</option></select>
  <select class="ob-in" name="sort" data-autosubmit aria-label="Sort"><option value="newest"<?= $sort==='newest'?' selected':'' ?>>Newest</option><option value="reward_desc"<?= $sort==='reward_desc'?' selected':'' ?>>Highest reward</option><option value="reward_asc"<?= $sort==='reward_asc'?' selected':'' ?>>Lowest reward</option><option value="deadline"<?= $sort==='deadline'?' selected':'' ?>>Deadline soonest</option></select>
  <input type="hidden" name="view" value="<?= e($view === 'list' ? 'list' : '') ?>">
  <button class="ob-btn ghost" type="submit">Apply</button>
  <div class="ob-seg" role="group" aria-label="Layout"><a class="<?= $view==='grid'?'on':'' ?>" href="<?= e($link(['view'=>''])) ?>" aria-label="Grid view" title="Grid"><?= ob_icon('tiles', 16) ?></a><a class="<?= $view==='list'?'on':'' ?>" href="<?= e($link(['view'=>'list'])) ?>" aria-label="List view" title="List"><?= ob_icon('list', 16) ?></a></div>
</form>
<?php if ($skills): ?><div class="ob-chips" style="margin-bottom:18px" aria-label="Filter by language or skill"><a class="ob-chip<?= !$skillF?' on':'' ?>" href="<?= e($link(['skill'=>''])) ?>">All skills</a><?php foreach ($skills as $sk): ?><a class="ob-chip<?= $skillF===(int)$sk['id']?' on':'' ?>" href="<?= e($link(['skill'=>(int)$sk['id']])) ?>"><?= e($sk['name']) ?></a><?php endforeach; ?></div><?php endif; ?>

<p class="ob-muted" style="margin:0 0 14px"><?= number_format($total) ?> bount<?= $total===1?'y':'ies' ?> found<?= $demoMatches ? ' · includes clearly labeled demo examples' : '' ?><?= $filtered ? ' · <a href="/OpenBounty/bounties/">Clear filters</a>' : '' ?></p>
<?php if (!$rows && !$error): ?>
  <?= $filtered ? ob_empty('search', 'No bounties match these filters', 'Try a broader search, a different skill, or clear the filters.', '<a class="ob-btn ghost" href="/OpenBounty/bounties/">Clear filters</a>') : ob_empty('flag', 'The board is ready for its first bounty', 'Be the first to share a useful software task with the community.', '<a class="ob-btn" href="/OpenBounty/bounties/create.php"'.$postAttr.'>Post the first bounty</a>') ?>
<?php else: ?>
  <div class="ob-bounties<?= $view==='list'?' list':'' ?>"><?php foreach ($rows as $b) echo ob_bounty_card($b); ?></div>
  <?= ob_pager($total, $per, $page, $params) ?>
<?php endif; ?>
<?php require __DIR__.'/../includes/footer.php'; ?>
