<?php
require __DIR__.'/../includes/bootstrap.php'; require_login(); $u = current_user();
$isReviewer = in_array($u['role'], ['reviewer','admin'], true) ? 1 : 0;
$tab = (($_GET['tab'] ?? '') === 'done') ? 'done' : 'pending';
$bountyF = filter_input(INPUT_GET, 'bounty', FILTER_VALIDATE_INT) ?: 0;
$page = ob_page_param(); $per = 10; $rows = []; $total = 0; $pendingN = 0;
$statusSql = $tab === 'pending' ? "s.status IN ('submitted','in_review','changes_requested')" : "s.status IN ('approved','rejected')";
try {
  $base = "FROM submissions s JOIN bounties b ON b.id=s.bounty_id JOIN users d ON d.id=s.contributor_id WHERE (b.creator_id=:uid OR :rv=1) AND s.contributor_id<>:uid2 AND ";
  $args = [':uid'=>$u['id'], ':rv'=>$isReviewer, ':uid2'=>$u['id']];
  $bf = ''; if ($bountyF) { $bf = ' AND b.id=:bid'; $args[':bid'] = $bountyF; }
  $s = db()->prepare("SELECT COUNT(*) $base s.status IN ('submitted','in_review','changes_requested')$bf"); $s->execute($args); $pendingN = (int)$s->fetchColumn();
  $s = db()->prepare("SELECT COUNT(*) $base $statusSql$bf"); $s->execute($args); $total = (int)$s->fetchColumn(); $off = ($page - 1) * $per;
  $s = db()->prepare("SELECT s.*,b.id bid,b.title,b.reward_xlm,b.status bstatus,d.id did,d.full_name dev,(SELECT COUNT(*) FROM submissions z WHERE z.contributor_id=d.id AND z.status='approved') dev_done $base $statusSql$bf ORDER BY s.created_at ".($tab === 'pending' ? 'ASC' : 'DESC')." LIMIT $per OFFSET $off"); $s->execute($args); $rows = $s->fetchAll();
} catch (Throwable $e) { error_log($e->getMessage()); }
$here = '/OpenBounty/reviews/'.($tab === 'done' ? '?tab=done' : '');
$pageTitle = 'Reviews'; require __DIR__.'/../includes/header.php';
echo ob_page_head('Reviews', $isReviewer ? 'Submissions awaiting a decision across the platform.' : 'Submissions to your bounties awaiting your decision.');
?>
<nav class="ob-tabs"><a class="<?= $tab==='pending'?'on':'' ?>" href="?<?= $bountyF ? 'bounty='.$bountyF : '' ?>">Awaiting review <span class="ob-muted">(<?= $pendingN ?>)</span></a><a class="<?= $tab==='done'?'on':'' ?>" href="?tab=done<?= $bountyF ? '&amp;bounty='.$bountyF : '' ?>">Decided</a></nav>
<?php if (!$rows): ?><?= ob_empty('check', $tab === 'pending' ? 'Nothing waiting for review' : 'No decided submissions yet', $tab === 'pending' ? 'New submissions to your bounties will show up here.' : '', $tab === 'pending' ? '<a class="ob-btn ghost" href="/OpenBounty/bounties/mine.php">My bounties</a>' : '') ?>
<?php else: ?><div class="ob-stack">
<?php foreach ($rows as $r): ?>
<article class="ob-card"><div style="display:flex;justify-content:space-between;gap:14px;flex-wrap:wrap"><div style="display:flex;gap:12px;align-items:center"><span class="ob-avatar md"><?= e(ob_initials($r['dev'])) ?></span><div><strong><a href="/OpenBounty/profile/?id=<?= (int)$r['did'] ?>" style="color:var(--text)"><?= e($r['dev']) ?></a></strong><div class="ob-muted" style="font-size:12.5px"><?= (int)$r['dev_done'] ?> approved contribution<?= (int)$r['dev_done'] === 1 ? '' : 's' ?> · <?= e(ob_ago($r['created_at'])) ?></div></div></div><div style="text-align:right"><?= ob_badge((string)$r['status']) ?><div class="ob-muted" style="font-size:12.5px;margin-top:4px"><a href="/OpenBounty/bounties/view.php?id=<?= (int)$r['bid'] ?>"><?= e($r['title']) ?></a> · <?= e(xlm($r['reward_xlm'])) ?> XLM</div></div></div>
<p style="margin:14px 0 6px"><a href="<?= e($r['submission_url']) ?>" target="_blank" rel="noopener noreferrer"><?= ob_icon('ext', 14) ?> <?= e(mb_strimwidth($r['submission_url'], 0, 90, '…')) ?></a></p>
<?php if ($r['notes']): ?><div class="ob-prose ob-muted"><?= e($r['notes']) ?></div><?php endif; ?>
<?php if ($tab === 'done' || $r['review_notes']): ?><div class="ob-note" style="margin-top:10px"><div><strong>Feedback</strong><div class="ob-prose"><?= $r['review_notes'] ? e($r['review_notes']) : 'No comment left.' ?></div></div></div><?php endif; ?>
<?php if ($tab === 'pending'): ?><form method="post" action="/OpenBounty/bounties/review.php" style="margin-top:14px"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="submission_id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="return" value="<?= e($here) ?>">
<label class="ob-l" for="fb<?= (int)$r['id'] ?>" style="margin-top:0">Feedback to the developer</label><textarea class="ob-in" id="fb<?= (int)$r['id'] ?>" name="review_notes" rows="2" maxlength="5000" placeholder="Explain your decision"></textarea>
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px"><button class="ob-btn good sm" name="decision" value="approved" type="submit" data-confirm="Accept this submission? Accepting does not send any payment.">Accept</button><button class="ob-btn warn sm" name="decision" value="changes_requested" type="submit">Request changes</button><button class="ob-btn danger sm" name="decision" value="rejected" type="submit" data-confirm="Reject this submission? The developer will see your feedback.">Reject</button></div></form><?php endif; ?></article>
<?php endforeach; ?></div><?= ob_pager($total, $per, $page, ['tab'=>$tab === 'done' ? 'done' : '', 'bounty'=>$bountyF ?: '']) ?><?php endif; ?>
<?php require __DIR__.'/../includes/footer.php'; ?>
