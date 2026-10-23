<?php
require __DIR__.'/../includes/bootstrap.php'; require_login(); $u = current_user(); $uid = (int)$u['id'];
$ready = ob_table_exists('notification_reads');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  check_csrf();
  if ($ready) {
    $all = ob_notifications($uid, 60); $valid = array_column($all, 'key'); $act = (string)($_POST['action'] ?? '');
    $keys = [];
    if ($act === 'all') { foreach ($all as $n) if (!$n['read']) $keys[] = $n['key']; }
    elseif ($act === 'one' && in_array((string)($_POST['key'] ?? ''), $valid, true)) $keys[] = (string)$_POST['key'];
    try { $ins = db()->prepare('INSERT IGNORE INTO notification_reads(user_id,nkey) VALUES(?,?)'); foreach ($keys as $k) $ins->execute([$uid, $k]); } catch (Throwable $e) { error_log($e->getMessage()); flash('Could not update notifications.', 'error'); }
  }
  header('Location: /OpenBounty/notifications/'.((($_POST['tab'] ?? '') === 'unread') ? '?tab=unread' : '')); exit;
}
$tab = (($_GET['tab'] ?? '') === 'unread') ? 'unread' : 'all';
$items = ob_notifications($uid, 60); $unread = count(array_filter($items, fn($n) => !$n['read']));
if ($tab === 'unread') $items = array_values(array_filter($items, fn($n) => !$n['read']));
$pageTitle = 'Notifications'; require __DIR__.'/../includes/header.php';
$markAll = ($ready && $unread) ? '<form method="post" data-no-loading><input type="hidden" name="csrf" value="'.e(csrf_token()).'"><input type="hidden" name="tab" value="'.e($tab).'"><button class="ob-btn ghost" name="action" value="all" type="submit">Mark all as read</button></form>' : '';
echo ob_page_head('Notifications', 'Bounty updates, review results and payment changes.', $markAll);
if (!$ready) echo ob_migration_notice();
?>
<nav class="ob-tabs"><a class="<?= $tab==='all'?'on':'' ?>" href="?">All</a><a class="<?= $tab==='unread'?'on':'' ?>" href="?tab=unread">Unread <span class="ob-muted">(<?= $unread ?>)</span></a></nav>
<?php if (!$items): ?><?= ob_empty('bell', $tab === 'unread' ? 'You are all caught up' : 'No notifications yet', 'Submission results, new submissions to your bounties and payment updates will appear here.') ?>
<?php else: ?><section class="ob-card"><ul class="ob-feed"><?php foreach ($items as $n): ?><li class="<?= $n['read'] ? 'read' : '' ?>"><span class="dot"></span><div style="flex:1"><span class="ob-badge gray" style="margin-right:6px"><?= e($n['kind']) ?></span><a href="<?= e($n['url']) ?>"><?= e($n['text']) ?></a><br><small><?= e(ob_ago($n['at'])) ?></small></div>
<?php if ($ready && !$n['read']): ?><form method="post" data-no-loading><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="key" value="<?= e($n['key']) ?>"><input type="hidden" name="tab" value="<?= e($tab) ?>"><button class="ob-btn ghost sm" name="action" value="one" type="submit">Mark read</button></form><?php endif; ?></li><?php endforeach; ?></ul></section><?php endif; ?>
<?php require __DIR__.'/../includes/footer.php'; ?>
