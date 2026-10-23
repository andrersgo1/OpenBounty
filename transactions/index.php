<?php
require __DIR__.'/../includes/bootstrap.php'; require_login(); $u = current_user(); $uid = (int)$u['id'];
$sel = "SELECT p.*,b.id bid,b.title bounty_title,pu.full_name payer,ru.full_name recipient,s.contributor_id FROM payments p JOIN bounties b ON b.id=p.bounty_id JOIN users pu ON pu.id=p.payer_id JOIN submissions s ON s.id=p.submission_id JOIN users ru ON ru.id=s.contributor_id";
$detail = null; $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($id) {
  $s = db()->prepare("$sel WHERE p.id=?"); $s->execute([$id]); $detail = $s->fetch();
  // Only the payer, the recipient or an admin may see a payment.
  if (!$detail || ((int)$detail['payer_id'] !== $uid && (int)$detail['contributor_id'] !== $uid && $u['role'] !== 'admin')) { http_response_code(404); exit('Payment not found.'); }
}
$q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100);
$status = in_array($_GET['status'] ?? '', ['pending','prepared','submitted','confirmed','verified','failed'], true) ? $_GET['status'] : '';
$dir = in_array($_GET['dir'] ?? '', ['sent','received'], true) ? $_GET['dir'] : '';
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['from'] ?? '')) ? $_GET['from'] : ''; $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['to'] ?? '')) ? $_GET['to'] : '';
$page = ob_page_param(); $per = 15; $rows = []; $total = 0;
if (!$detail) { try {
  $w = ['(p.payer_id=? OR s.contributor_id=?)']; $a = [$uid, $uid];
  if ($q !== '') { $l = '%'.addcslashes($q, '%_\\').'%'; $w[] = '(b.title LIKE ? OR p.transaction_hash LIKE ? OR p.recipient_wallet LIKE ? OR pu.full_name LIKE ? OR ru.full_name LIKE ?)'; array_push($a, $l, $l, $l, $l, $l); }
  if ($status) { $w[] = 'p.status=?'; $a[] = $status; }
  if ($dir === 'sent') { $w[] = 'p.payer_id=?'; $a[] = $uid; } elseif ($dir === 'received') { $w[] = 's.contributor_id=?'; $a[] = $uid; }
  if ($from) { $w[] = 'DATE(p.created_at)>=?'; $a[] = $from; } if ($to) { $w[] = 'DATE(p.created_at)<=?'; $a[] = $to; }
  $wh = implode(' AND ', $w);
  $c = db()->prepare("SELECT COUNT(*) FROM payments p JOIN bounties b ON b.id=p.bounty_id JOIN users pu ON pu.id=p.payer_id JOIN submissions s ON s.id=p.submission_id JOIN users ru ON ru.id=s.contributor_id WHERE $wh"); $c->execute($a); $total = (int)$c->fetchColumn(); $off = ($page - 1) * $per;
  $r = db()->prepare("$sel WHERE $wh ORDER BY p.created_at DESC LIMIT $per OFFSET $off"); $r->execute($a); $rows = $r->fetchAll();
} catch (Throwable $e) { error_log($e->getMessage()); } }
$pageTitle = $detail ? 'Payment details' : 'Transaction history'; require __DIR__.'/../includes/header.php';
if ($detail): $url = ob_tx_url($detail['transaction_hash']); $ver = $detail['verification_json'] ? json_decode((string)$detail['verification_json'], true) : null; ?>
<div class="ob-crumb"><a href="/OpenBounty/transactions/">Transaction history</a> / Payment #<?= (int)$detail['id'] ?></div>
<?= ob_page_head('Payment #'.(int)$detail['id'], $detail['bounty_title']) ?>
<div class="ob-split"><section class="ob-card"><dl class="ob-kv"><dt>Status</dt><dd><?= ob_badge((string)$detail['status']) ?></dd><dt>Amount</dt><dd><strong><?= e(xlm($detail['amount_xlm'])) ?> XLM</strong></dd><dt>Network</dt><dd>Stellar Testnet</dd><dt>Sender</dt><dd><?= e($detail['payer']) ?></dd><dt>Recipient</dt><dd><?= e($detail['recipient']) ?></dd><dt>Recipient address</dt><dd class="ob-mono"><?= e($detail['recipient_wallet']) ?></dd><dt>Bounty</dt><dd><a href="/OpenBounty/bounties/view.php?id=<?= (int)$detail['bid'] ?>"><?= e($detail['bounty_title']) ?></a></dd><dt>Transaction hash</dt><dd class="ob-mono"><?= $detail['transaction_hash'] ? e($detail['transaction_hash']) : '<span class="ob-muted">Not recorded</span>' ?></dd><dt>Created</dt><dd><?= e(ob_date($detail['created_at'], 'M j, Y g:i A')) ?></dd><dt>Updated</dt><dd><?= e(ob_date($detail['updated_at'], 'M j, Y g:i A')) ?></dd><?php if ($detail['failure_reason']): ?><dt>Failure reason</dt><dd><?= e($detail['failure_reason']) ?></dd><?php endif; ?></dl></section>
<aside class="ob-stack"><section class="ob-card"><h3>Explorer</h3><?php if ($url): ?><a class="ob-btn" style="margin-top:10px" href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer"><?= ob_icon('ext', 15) ?>View on Stellar Testnet</a><?php else: ?><p class="ob-muted" style="margin:8px 0 0">No explorer link is available because no transaction hash has been recorded.</p><?php endif; ?></section>
<section class="ob-card"><h3>Verification</h3><?php if ($detail['status'] === 'verified'): ?><p style="margin:8px 0 0"><?= ob_badge('verified') ?> Recorded as independently verified.</p><?php else: ?><p class="ob-muted" style="margin:8px 0 0">This payment is <strong><?= e(str_replace('_', ' ', $detail['status'])) ?></strong>, not verified.</p><?php endif; ?><?php if (is_array($ver)): ?><details class="ob-det" style="margin-top:10px"><summary>Verification record</summary><pre class="ob-mono" style="white-space:pre-wrap;word-break:break-all;margin:0"><?= e(json_encode($ver, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre></details><?php endif; ?></section></aside></div>
<?php else:
$params = ['q'=>$q,'status'=>$status,'dir'=>$dir,'from'=>$from,'to'=>$to];
echo ob_page_head('Transaction history', 'Every payment record you sent or received, straight from the database.'); ?>
<form class="ob-filters" method="get"><div class="ob-gsearch grow" style="max-width:none"><?= ob_icon('search', 16) ?><input type="search" name="q" value="<?= e($q) ?>" placeholder="Search bounty, hash, address or person" aria-label="Search transactions" maxlength="100"></div>
<select class="ob-in" name="dir" data-autosubmit aria-label="Direction"><option value="">Sent &amp; received</option><option value="sent"<?= $dir==='sent'?' selected':'' ?>>Sent</option><option value="received"<?= $dir==='received'?' selected':'' ?>>Received</option></select>
<select class="ob-in" name="status" data-autosubmit aria-label="Status"><option value="">Any status</option><?php foreach (['pending','prepared','submitted','confirmed','verified','failed'] as $sv): ?><option value="<?= $sv ?>"<?= $status===$sv?' selected':'' ?>><?= ucfirst($sv) ?></option><?php endforeach; ?></select>
<input class="ob-in" type="date" name="from" value="<?= e($from) ?>" aria-label="From date"><input class="ob-in" type="date" name="to" value="<?= e($to) ?>" aria-label="To date"><button class="ob-btn ghost" type="submit">Filter</button></form>
<?php if (!$rows): ?><?= ($q || $status || $dir || $from || $to) ? ob_empty('search', 'No transactions match', 'Try clearing a filter.', '<a class="ob-btn ghost" href="/OpenBounty/transactions/">Clear filters</a>') : ob_empty('list', 'No transactions yet', 'Payment records appear here once a payment is recorded for an approved bounty. Nothing is shown unless it exists.') ?>
<?php else: ?><div class="ob-card flush"><div class="ob-tw"><table class="ob-table"><thead><tr><th>Date</th><th>Bounty</th><th>From → To</th><th class="num">Amount</th><th>Network</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): $x = ob_tx_url($r['transaction_hash']); ?><tr><td><?= e(ob_date($r['created_at'])) ?></td><td><a class="t" href="/OpenBounty/transactions/?id=<?= (int)$r['id'] ?>"><?= e($r['bounty_title']) ?></a></td><td><?= e($r['payer']) ?> → <?= e($r['recipient']) ?></td><td class="num"><?= e(xlm($r['amount_xlm'])) ?> XLM</td><td>Stellar Testnet</td><td><?= ob_badge((string)$r['status']) ?></td><td><a class="ob-btn ghost sm" href="/OpenBounty/transactions/?id=<?= (int)$r['id'] ?>">Details</a><?php if ($x): ?> <a class="ob-btn ghost sm" href="<?= e($x) ?>" target="_blank" rel="noopener noreferrer" aria-label="View on explorer"><?= ob_icon('ext', 14) ?></a><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></div>
<?= ob_pager($total, $per, $page, $params) ?><?php endif; endif; ?>
<?php require __DIR__.'/../includes/footer.php'; ?>
