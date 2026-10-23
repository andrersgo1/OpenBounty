<?php
require __DIR__.'/../includes/bootstrap.php'; $u = current_user();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT); if (!$id) { http_response_code(400); exit('Invalid bounty ID.'); }
$hasDiff = ob_col_exists('bounties', 'difficulty');
$s = db()->prepare('SELECT b.*,u.full_name creator,u.bio creator_bio,s.name skill FROM bounties b JOIN users u ON u.id=b.creator_id LEFT JOIN skills s ON s.id=b.skill_id WHERE b.id=?'); $s->execute([$id]); $b = $s->fetch();
if (!$b) { http_response_code(404); exit('Bounty not found.'); }
$isOwner = $u && (int)$b['creator_id'] === (int)$u['id']; $isReviewer = $u && in_array($u['role'], ['reviewer','admin'], true); $canReview = $u && ($isOwner || $isReviewer);
// Drafts and unpublished records are private to their creator and authorized reviewers.
if (!in_array($b['status'], ['open','in_review','approved','payment_pending','paid','closed'], true) && !$canReview) { http_response_code(404); exit('Bounty not found.'); }
// Submission URLs and notes are private: owners/reviewers see all; contributors see only their own.
$subs = []; $mySub = null;
if ($u) {
  if ($canReview) { $s = db()->prepare('SELECT s.*,u.full_name contributor FROM submissions s JOIN users u ON u.id=s.contributor_id WHERE s.bounty_id=? ORDER BY s.created_at DESC'); $s->execute([$id]); }
  else { $s = db()->prepare('SELECT s.*,u.full_name contributor FROM submissions s JOIN users u ON u.id=s.contributor_id WHERE s.bounty_id=? AND s.contributor_id=? ORDER BY s.created_at DESC'); $s->execute([$id, $u['id']]); }
  $subs = $s->fetchAll(); foreach ($subs as $sub) { if ((int)$sub['contributor_id'] === (int)$u['id']) { $mySub = $sub; break; } }
}
$hasApproved = false; foreach ($subs as $sub) { if ($sub['status'] === 'approved') $hasApproved = true; }
if ($isOwner && !$hasApproved) { $c = db()->prepare("SELECT COUNT(*) FROM submissions WHERE bounty_id=? AND status='approved'"); $c->execute([$id]); $hasApproved = (int)$c->fetchColumn() > 0; }
$cs = db()->prepare("SELECT COUNT(*) total, COALESCE(SUM(status IN ('approved','payment_pending','paid')),0) done FROM bounties WHERE creator_id=? AND status<>'draft'"); $cs->execute([$b['creator_id']]); $cstat = $cs->fetch();
$canEdit = ($isOwner || ($u && $u['role'] === 'admin')) && !in_array($b['status'], ['approved','payment_pending','paid'], true);
$canClose = ($isOwner || ($u && $u['role'] === 'admin')) && in_array($b['status'], ['open','draft','in_review'], true) && !$hasApproved;
$canReopen = ($isOwner || ($u && $u['role'] === 'admin')) && $b['status'] === 'closed';
$pending = array_filter($subs, fn($x) => in_array($x['status'], ['submitted','in_review','changes_requested'], true));
$here = '/OpenBounty/bounties/view.php?id='.$id;
$criteria = array_values(array_filter(array_map('trim', preg_split('/\R/', (string)$b['acceptance_criteria']))));
$pageTitle = $b['title']; require __DIR__.'/../includes/header.php';
?>
<div class="ob-crumb"><a href="/OpenBounty/bounties/">Explore bounties</a> / <?= e($b['skill'] ?? 'General') ?></div>
<div class="ob-split">
 <div class="ob-stack">
  <section class="ob-card">
    <div class="ob-tags" style="margin-bottom:12px"><span class="ob-skill"><?= e($b['skill'] ?? 'General') ?></span><?= ob_badge((string)$b['status']) ?><?= $hasDiff ? ob_diff($b['difficulty'] ?? null) : '' ?></div>
    <h2 style="font-size:26px;margin:0 0 16px"><?= e($b['title']) ?></h2>
    <div class="ob-meta"><div><span>Reward</span><strong><?= e(xlm($b['reward_xlm'])) ?> XLM</strong></div><div><span>Deadline</span><strong><?= $b['deadline'] ? e(ob_date($b['deadline'])) : '—' ?></strong> <small><?= ob_deadline($b['deadline']) ?></small></div><div><span>Posted</span><strong><?= e(ob_date($b['created_at'])) ?></strong></div><div><span>Network</span><strong>Stellar Testnet</strong></div></div>
  </section>
  <section class="ob-card"><h3>Description</h3><div class="ob-prose" style="margin-top:10px"><?= e($b['description']) ?></div></section>
  <section class="ob-card"><h3>Acceptance criteria</h3>
    <?php if (count($criteria) > 1): ?><ul style="margin:10px 0 0;padding-left:20px;line-height:1.8"><?php foreach ($criteria as $c): ?><li><?= e(ltrim($c, "-*• \t")) ?></li><?php endforeach; ?></ul><?php else: ?><div class="ob-prose" style="margin-top:10px"><?= e($b['acceptance_criteria']) ?></div><?php endif; ?></section>

  <?php if ($canReview || $mySub): ?>
  <section class="ob-card" id="submissions"><div class="ob-card-h"><h3><?= $canReview ? 'Submissions ('.count($subs).')' : 'Your submission' ?></h3></div>
    <?php if (!$subs): ?><?= ob_empty('inbox', 'No submissions yet', 'Work submitted to this bounty will appear here.') ?><?php endif; ?>
    <?php foreach ($subs as $sub): ?>
      <div style="border-top:1px solid var(--line);padding:16px 0">
        <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap"><strong><?= e($sub['contributor']) ?></strong><span><?= ob_badge((string)$sub['status']) ?> <small class="ob-muted"><?= e(ob_ago($sub['created_at'])) ?></small></span></div>
        <p style="margin:8px 0"><a href="<?= e($sub['submission_url']) ?>" target="_blank" rel="noopener noreferrer"><?= ob_icon('ext', 14) ?> Open submission evidence</a></p>
        <?php if ($sub['notes']): ?><div class="ob-prose ob-muted"><?= e($sub['notes']) ?></div><?php endif; ?>
        <?php if ($sub['review_notes']): ?><div class="ob-note" style="margin-top:10px"><div><strong>Reviewer feedback</strong><div class="ob-prose"><?= e($sub['review_notes']) ?></div></div></div><?php endif; ?>
        <?php if ($canReview && (int)$sub['contributor_id'] !== (int)$u['id'] && in_array($sub['status'], ['submitted','in_review','changes_requested'], true)): ?>
          <form method="post" action="/OpenBounty/bounties/review.php" style="margin-top:12px"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="submission_id" value="<?= (int)$sub['id'] ?>"><input type="hidden" name="return" value="<?= e($here) ?>">
            <label class="ob-l" for="rn<?= (int)$sub['id'] ?>" style="margin-top:0">Review comment</label><textarea class="ob-in" id="rn<?= (int)$sub['id'] ?>" name="review_notes" rows="2" maxlength="5000" placeholder="Explain your decision"></textarea>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px"><button class="ob-btn good sm" name="decision" value="approved" type="submit" data-confirm="Approve this submission? This does not send any payment.">Accept</button><button class="ob-btn warn sm" name="decision" value="changes_requested" type="submit">Request changes</button><button class="ob-btn danger sm" name="decision" value="rejected" type="submit" data-confirm="Reject this submission?">Reject</button></div></form>
        <?php endif; ?>
        <?php if ($isOwner && $sub['status'] === 'approved' && in_array($b['status'], ['approved', 'payment_pending'], true)): ?>
          <form method="post" action="/OpenBounty/payments/create-intent.php" style="margin-top:12px">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="submission_id" value="<?= (int)$sub['id'] ?>">
            <button class="ob-btn good sm" type="submit" data-confirm="Continue to Freighter to review and authorize this Testnet payment?">
              Pay contributor with Freighter
            </button>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>
 </div>

 <aside class="ob-stack">
  <section class="ob-card glow"><h3>Actions</h3><div style="display:grid;gap:10px;margin-top:12px">
    <?php if (!$u): ?><p class="ob-muted" style="margin:0">Sign in to submit work or express interest.</p><button class="ob-btn" type="button" data-open-auth="register">I'm interested</button>
    <?php elseif ($isOwner): ?><p class="ob-muted" style="margin:0">You created this bounty, so you cannot submit work to it.</p>
    <?php elseif ($b['status'] !== 'open'): ?><p class="ob-muted" style="margin:0">This bounty is not accepting submissions.</p>
    <?php elseif ($mySub): ?><p style="margin:0">Your submission: <?= ob_badge((string)$mySub['status']) ?></p>
    <?php else: ?><a class="ob-btn" href="/OpenBounty/bounties/submit.php?id=<?= $id ?>"><?= ob_icon('upload', 16) ?>Submit work</a><?php endif; ?>
    <?php if ($canReview && $pending): ?><a class="ob-btn ghost" href="#submissions"><?= ob_icon('check', 16) ?>Review submissions (<?= count($pending) ?>)</a><?php endif; ?>
    <?php if ($canEdit): ?><a class="ob-btn ghost" href="/OpenBounty/bounties/edit.php?id=<?= $id ?>">Edit bounty</a><?php endif; ?>
    <?php if ($canClose): ?><form method="post" action="/OpenBounty/bounties/manage.php"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="bounty_id" value="<?= $id ?>"><input type="hidden" name="return" value="<?= e($here) ?>"><button class="ob-btn danger" style="width:100%" name="action" value="close" type="submit" data-confirm="Close this bounty? It will stop accepting submissions.">Close bounty</button></form><?php endif; ?>
    <?php if ($canReopen): ?><form method="post" action="/OpenBounty/bounties/manage.php"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="bounty_id" value="<?= $id ?>"><input type="hidden" name="return" value="<?= e($here) ?>"><button class="ob-btn ghost" style="width:100%" name="action" value="reopen" type="submit">Reopen bounty</button></form><?php endif; ?>
  </div><p class="ob-hint" style="margin-top:12px">Approving work never sends funds on its own. Payments are shown only once they are recorded and verified.</p></section>
  <section class="ob-card"><h3>Posted by</h3><div style="display:flex;gap:12px;align-items:center;margin:12px 0"><span class="ob-avatar md"><?= e(ob_initials($b['creator'])) ?></span><div><strong><a href="/OpenBounty/profile/?id=<?= (int)$b['creator_id'] ?>" style="color:var(--text)"><?= e($b['creator']) ?></a></strong><div class="ob-muted" style="font-size:12.5px"><?= (int)$cstat['total'] ?> bounties posted · <?= (int)$cstat['done'] ?> completed</div></div></div>
    <?php if (!empty($b['creator_bio'])): ?><p class="ob-muted" style="margin:0"><?= e(mb_strimwidth((string)$b['creator_bio'], 0, 180, '…')) ?></p><?php endif; ?><a class="ob-btn ghost sm" style="margin-top:12px" href="/OpenBounty/profile/?id=<?= (int)$b['creator_id'] ?>">View profile</a></section>
 </aside>
</div>
<?php require __DIR__.'/../includes/footer.php'; ?>
