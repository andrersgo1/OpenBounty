<?php
require __DIR__.'/../includes/bootstrap.php'; $u = current_user(); $uid = $u ? (int)$u['id'] : 0;
$awaiting = []; $recent = []; $counts = [];
if ($u) { try {
  $q = db()->prepare("SELECT s.id sid,b.id bid,b.title,b.reward_xlm,b.creator_id,s.contributor_id,cu.full_name dev,cr.full_name creator,p.status pstatus FROM submissions s JOIN bounties b ON b.id=s.bounty_id JOIN users cu ON cu.id=s.contributor_id JOIN users cr ON cr.id=b.creator_id LEFT JOIN payments p ON p.submission_id=s.id WHERE s.status='approved' AND (b.creator_id=? OR s.contributor_id=?) AND (p.id IS NULL OR p.status<>'verified') ORDER BY s.reviewed_at DESC LIMIT 20"); $q->execute([$uid, $uid]); $awaiting = $q->fetchAll();
  $q = db()->prepare("SELECT p.status, COUNT(*) n FROM payments p JOIN submissions s ON s.id=p.submission_id WHERE p.payer_id=? OR s.contributor_id=? GROUP BY p.status"); $q->execute([$uid, $uid]); $counts = array_column($q->fetchAll(), 'n', 'status');
  $q = db()->prepare("SELECT p.id,p.status,p.amount_xlm,p.transaction_hash,p.updated_at,b.title FROM payments p JOIN bounties b ON b.id=p.bounty_id JOIN submissions s ON s.id=p.submission_id WHERE p.payer_id=? OR s.contributor_id=? ORDER BY p.updated_at DESC LIMIT 6"); $q->execute([$uid, $uid]); $recent = $q->fetchAll();
} catch (Throwable $e) { error_log($e->getMessage()); } }
$pageTitle = 'Payments'; require __DIR__.'/../includes/header.php';
echo ob_page_head('Payments', 'Proof that goes beyond “paid”: approved work, a recorded payment, then a verified payout.', $u ? '<a class="ob-btn ghost" href="/OpenBounty/transactions/">Transaction history</a>' : '');
?>
<div class="ob-steps" style="margin-bottom:18px"><div><span>01</span><h3>Work approved</h3><p class="ob-muted" style="margin:4px 0 0">The creator or reviewer records the decision.</p></div><div><span>02</span><h3>Payment recorded</h3><p class="ob-muted" style="margin:4px 0 0">A Testnet payment is initiated and stored with its transaction hash.</p></div><div><span>03</span><h3>Payout verified</h3><p class="ob-muted" style="margin:4px 0 0">Recipient, amount and transaction are independently checked.</p></div></div>
<div class="ob-note" style="margin-bottom:18px"><div><strong>Payments run through the Paiflow Testnet deployment.</strong> Bounty payouts split 90% to the contributor and 10% to the community treasury. Nothing is shown as paid until the on-chain payout is independently verified, and a verified transaction never triggers a second payment.</div></div>
<?php
$recoverable = 0;
if ($u) { try {
  $q = db()->prepare("SELECT COUNT(*) n FROM payments p JOIN bounties b ON b.id=p.bounty_id JOIN submissions s ON s.id=p.submission_id WHERE p.payer_id=? AND p.status IN ('pending','prepared','submitted','confirmed') AND b.status='payment_pending' AND s.status='approved'");
  $q->execute([$uid]);
  $row = $q->fetch();
  $recoverable = (int)($row['n'] ?? 0);
} catch (Throwable $e) { error_log($e->getMessage()); } }
if ($recoverable > 0): ?>
<div class="ob-note" style="margin-bottom:18px"><div><strong><?= $recoverable ?> payment<?= $recoverable === 1 ? '' : 's' ?> awaiting verification.</strong> A transaction may already be on Testnet. <a href="/OpenBounty/payments/recover.php">Resume verification</a> — no new payment is created.</div></div>
<?php endif; ?>
<?php if (!$u): ?><?= ob_empty('card', 'Sign in to see your payments', 'Your approved work and payment records will appear here.', '<button class="ob-btn" type="button" data-open-auth="login">Log in</button>') ?>
<?php else: ?>
<div class="ob-split"><section class="ob-card flush"><div class="ob-card-h" style="padding:18px 20px 0"><h3>Awaiting payment</h3></div>
 <?php if (!$awaiting): ?><div style="padding:0 20px 20px"><p class="ob-muted" style="margin:0">No approved work is waiting for payment.</p></div><?php else: ?><div class="ob-tw"><table class="ob-table"><thead><tr><th>Bounty</th><th>Developer</th><th>Creator</th><th>Payment</th><th class="num">Reward</th></tr></thead><tbody><?php foreach ($awaiting as $r): ?><tr><td><a class="t" href="/OpenBounty/bounties/view.php?id=<?= (int)$r['bid'] ?>"><?= e($r['title']) ?></a></td><td><?= e($r['dev']) ?><?= (int)$r['contributor_id'] === $uid ? ' <span class="ob-badge purple">You</span>' : '' ?></td><td><?= e($r['creator']) ?></td><td><?= $r['pstatus'] ? ob_badge((string)$r['pstatus']) : '<span class="ob-badge gray">Not recorded</span>' ?></td><td class="num"><?= e(xlm($r['reward_xlm'])) ?> XLM</td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
 <aside class="ob-stack"><section class="ob-card"><h3>Payment records</h3><?php if (!$counts): ?><p class="ob-muted" style="margin:8px 0 0">None yet.</p><?php else: ?><div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px"><?php foreach ($counts as $k => $n): ?><span><?= ob_badge((string)$k) ?> <strong><?= (int)$n ?></strong></span><?php endforeach; ?></div><?php endif; ?></section>
 <section class="ob-card"><h3>Latest</h3><?php if (!$recent): ?><p class="ob-muted" style="margin:8px 0 0">No recorded payments.</p><?php else: ?><ul class="ob-feed"><?php foreach ($recent as $r): ?><li><span class="dot"></span><div><a href="/OpenBounty/transactions/?id=<?= (int)$r['id'] ?>"><?= e($r['title']) ?></a><br><small><?= e(xlm($r['amount_xlm'])) ?> XLM · <?= e(ob_label((string)$r['status'])) ?> · <?= e(ob_ago($r['updated_at'])) ?></small></div></li><?php endforeach; ?></ul><?php endif; ?></section></aside></div>
<?php endif; ?>
<?php require __DIR__.'/../includes/footer.php'; ?>
