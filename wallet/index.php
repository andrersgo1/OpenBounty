<?php
require __DIR__.'/../includes/bootstrap.php'; require_login(); $u = current_user(); $uid = (int)$u['id']; $errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  check_csrf(); $act = (string)($_POST['action'] ?? '');
  if ($act === 'remove') { db()->prepare('UPDATE users SET wallet_address=NULL WHERE id=?')->execute([$uid]); audit($uid, 'wallet_address_removed', 'user', $uid); flash('Saved wallet address removed.', 'success'); header('Location: /OpenBounty/wallet/'); exit; }
  $addr = strtoupper(trim((string)($_POST['wallet_address'] ?? '')));
  if (!ob_valid_stellar_key($addr)) $errors[] = 'Enter a valid Stellar public address (starts with G, 56 characters). Never enter a secret key or recovery phrase.';
  else {
    try { db()->prepare('UPDATE users SET wallet_address=? WHERE id=?')->execute([$addr, $uid]); audit($uid, 'wallet_address_saved', 'user', $uid); flash('Wallet address saved. This does not connect or verify a wallet.', 'success'); header('Location: /OpenBounty/wallet/'); exit; }
    catch (Throwable $e) { error_log($e->getMessage()); $errors[] = 'Could not save the address. Please try again.'; }
  }
}
$me = ob_user_row($uid); $addr = (string)($me['wallet_address'] ?? '');
$pending = '0'; $earned = '0'; $verifiedN = 0; $recent = [];
try {
  $q = db()->prepare("SELECT COALESCE(SUM(b.reward_xlm),0) FROM submissions s JOIN bounties b ON b.id=s.bounty_id LEFT JOIN payments p ON p.submission_id=s.id WHERE s.contributor_id=? AND s.status='approved' AND (p.id IS NULL OR p.status<>'verified')"); $q->execute([$uid]); $pending = (string)$q->fetchColumn();
  $q = db()->prepare("SELECT COALESCE(SUM(p.amount_xlm),0) amt, COUNT(*) n FROM payments p JOIN submissions s ON s.id=p.submission_id WHERE s.contributor_id=? AND p.status='verified'"); $q->execute([$uid]); $r = $q->fetch(); $earned = (string)$r['amt']; $verifiedN = (int)$r['n'];
  $q = db()->prepare("SELECT p.id,p.amount_xlm,p.status,p.transaction_hash,p.updated_at,b.title FROM payments p JOIN bounties b ON b.id=p.bounty_id JOIN submissions s ON s.id=p.submission_id WHERE (s.contributor_id=? OR p.payer_id=?) ORDER BY p.updated_at DESC LIMIT 5"); $q->execute([$uid, $uid]); $recent = $q->fetchAll();
} catch (Throwable $e) { error_log($e->getMessage()); }
$pageTitle = 'Wallet'; require __DIR__.'/../includes/header.php';
echo ob_page_head('Wallet', 'Your payout address, pending rewards and verified payments on Stellar Testnet.');
?>
<div class="ob-split"><div class="ob-stack">
 <section class="ob-card glow"><div class="ob-card-h"><h3>Wallet connection</h3><span class="ob-badge purple">Stellar Testnet</span></div>
  <div style="display:flex;gap:14px;align-items:center"><span class="ob-ico" style="width:46px;height:46px;border-radius:14px;background:var(--accent-soft);color:var(--accent2);display:grid;place-items:center"><?= ob_icon('wallet', 22) ?></span><div><strong>Not connected</strong><div class="ob-muted">This build has no wallet signing or Paiflow connection, so no live wallet status or balance can be shown.</div></div></div>
  <div class="ob-grid ob-g2" style="margin-top:18px"><div><span class="ob-muted" style="font-size:12px">Available balance</span><div style="font-size:18px;font-weight:600">Unavailable</div><div class="ob-hint">Requires a connected wallet integration (future work).</div></div><div><span class="ob-muted" style="font-size:12px">Saved payout address</span><div class="ob-mono" style="margin-top:4px;word-break:break-all"><?= $addr !== '' ? e($addr) : 'None saved' ?></div></div></div></section>
 <section class="ob-card"><h3>Payout address</h3><p class="ob-muted" style="margin:4px 0 0">Saved so creators know where to send approved rewards. Saving an address does not connect or verify a wallet.</p>
  <?php if ($errors): ?><div class="ob-flash error" style="margin-top:12px" role="alert"><?php foreach ($errors as $er) echo '<div>'.e($er).'</div>'; ?></div><?php endif; ?>
  <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save"><label class="ob-l" for="wa">Stellar public address</label><input class="ob-in ob-mono" id="wa" name="wallet_address" maxlength="56" value="<?= e($addr) ?>" placeholder="G…" autocomplete="off" spellcheck="false"><div style="display:flex;gap:8px;margin-top:14px;flex-wrap:wrap"><button class="ob-btn" type="submit">Save address</button></div></form>
  <?php if ($addr !== ''): ?><form method="post" style="margin-top:8px"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><button class="ob-btn danger sm" name="action" value="remove" type="submit" data-confirm="Remove your saved wallet address?">Remove saved address</button></form><?php endif; ?></section>
 <section class="ob-card flush"><div class="ob-card-h" style="padding:18px 20px 0"><h3>Recent payments</h3><a href="/OpenBounty/transactions/">All transactions</a></div>
  <?php if (!$recent): ?><div style="padding:0 20px 20px"><p class="ob-muted" style="margin:0">No payments recorded yet. Only payments that exist in the database are listed.</p></div><?php else: ?><div class="ob-tw"><table class="ob-table"><tbody><?php foreach ($recent as $r): ?><tr><td><a class="t" href="/OpenBounty/transactions/?id=<?= (int)$r['id'] ?>"><?= e($r['title']) ?></a><div class="ob-muted" style="font-size:12px"><?= e(ob_ago($r['updated_at'])) ?></div></td><td><?= ob_badge((string)$r['status']) ?></td><td class="num"><?= e(xlm($r['amount_xlm'])) ?> XLM</td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
 </div>
 <aside class="ob-stack"><div class="ob-card ob-stat"><span>Pending rewards</span><strong><?= e(xlm($pending)) ?> XLM</strong><small>Approved work not yet verified as paid</small></div><div class="ob-card ob-stat"><span>Verified earnings</span><strong><?= e(xlm($earned)) ?> XLM</strong><small><?= $verifiedN ?> verified payment<?= $verifiedN === 1 ? '' : 's' ?></small></div>
  <div class="ob-note"><div><strong>Paiflow status</strong><br>Paiflow is not configured in this build. No payment is initiated, and nothing is marked paid without independent verification.</div></div></aside></div>
<?php require __DIR__.'/../includes/footer.php'; ?>
