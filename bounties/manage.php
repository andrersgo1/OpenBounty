<?php
// Creator actions on a bounty: close or reopen. POST only, CSRF protected, ownership checked.
require __DIR__.'/../includes/bootstrap.php'; require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method not allowed.'); }
check_csrf(); $u = current_user();
$id = filter_input(INPUT_POST, 'bounty_id', FILTER_VALIDATE_INT); $action = (string)($_POST['action'] ?? '');
$back = safe_path((string)($_POST['return'] ?? ''), '/OpenBounty/bounties/mine.php');
if (!$id || !in_array($action, ['close','reopen'], true)) { flash('Invalid request.', 'error'); header('Location: '.$back); exit; }
$s = db()->prepare('SELECT id,creator_id,status FROM bounties WHERE id=?'); $s->execute([$id]); $b = $s->fetch();
if (!$b) { http_response_code(404); exit('Bounty not found.'); }
if ((int)$b['creator_id'] !== (int)$u['id'] && $u['role'] !== 'admin') { http_response_code(403); exit('Only the bounty creator can manage this bounty.'); }
if ($action === 'close') {
  if (!in_array($b['status'], ['open','draft','in_review'], true)) { flash('Only open, draft or in-review bounties can be closed.', 'error'); }
  else {
    // A bounty with an approved submission is not closed here, so earned rewards are never orphaned.
    $c = db()->prepare("SELECT COUNT(*) FROM submissions WHERE bounty_id=? AND status='approved'"); $c->execute([$id]);
    if ((int)$c->fetchColumn() > 0) flash('This bounty has an approved submission and cannot be closed.', 'error');
    else { db()->prepare("UPDATE bounties SET status='closed' WHERE id=? AND status IN ('open','draft','in_review')")->execute([$id]); audit((int)$u['id'], 'bounty_closed', 'bounty', $id); flash('Bounty closed.', 'success'); }
  }
} else {
  if ($b['status'] !== 'closed') flash('Only closed bounties can be reopened.', 'error');
  else { db()->prepare("UPDATE bounties SET status='open' WHERE id=? AND status='closed'")->execute([$id]); audit((int)$u['id'], 'bounty_reopened', 'bounty', $id); flash('Bounty reopened.', 'success'); }
}
header('Location: '.$back); exit;
