<?php
require __DIR__.'/../includes/bootstrap.php'; require __DIR__.'/../includes/bounty_form.php'; require_login(); $u = current_user();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT); if (!$id) { http_response_code(400); exit('Invalid bounty ID.'); }
$s = db()->prepare('SELECT * FROM bounties WHERE id=?'); $s->execute([$id]); $b = $s->fetch(); if (!$b) { http_response_code(404); exit('Bounty not found.'); }
// Only the creator (or an admin) may edit.
if ((int)$b['creator_id'] !== (int)$u['id'] && $u['role'] !== 'admin') { http_response_code(403); exit('Only the bounty creator can edit this bounty.'); }
// Once work is approved or paid, the terms are locked so rewards cannot be changed after the fact.
if (in_array($b['status'], ['approved','payment_pending','paid'], true)) { flash('This bounty can no longer be edited because work has been approved.', 'error'); header('Location: /OpenBounty/bounties/view.php?id='.$id); exit; }
try { $skills = db()->query('SELECT id,name FROM skills ORDER BY name')->fetchAll(); } catch (Throwable $e) { $skills = []; }
$hasDiff = ob_col_exists('bounties', 'difficulty'); $errors = [];
$v = ['title'=>$b['title'],'description'=>$b['description'],'acceptance_criteria'=>$b['acceptance_criteria'],'reward_xlm'=>xlm($b['reward_xlm']),'deadline'=>$b['deadline'] ? date('Y-m-d\TH:i', strtotime($b['deadline'])) : '','skill_id'=>(string)($b['skill_id'] ?? ''),'difficulty'=>(string)($b['difficulty'] ?? '')];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  check_csrf(); [$v, $errors] = ob_bounty_input($skills);
  if (!$errors) {
    $newStatus = ($b['status'] === 'draft' && ($_POST['publish'] ?? '') === 'publish') ? 'open' : $b['status'];
    $dt = $v['deadline'] !== '' ? date('Y-m-d H:i:s', strtotime($v['deadline'])) : null;
    $set = 'title=?,description=?,acceptance_criteria=?,skill_id=?,reward_xlm=?,status=?,deadline=?';
    $vals = [$v['title'], $v['description'], $v['acceptance_criteria'], $v['skill_id'] !== '' ? (int)$v['skill_id'] : null, number_format((float)$v['reward_xlm'], 7, '.', ''), $newStatus, $dt];
    if ($hasDiff) { $set .= ',difficulty=?'; $vals[] = $v['difficulty'] !== '' ? $v['difficulty'] : null; }
    $vals[] = $id;
    $s = db()->prepare("UPDATE bounties SET $set WHERE id=? AND status NOT IN ('approved','payment_pending','paid')"); $s->execute($vals);
    audit((int)$u['id'], 'bounty_updated', 'bounty', $id); flash($newStatus === 'open' && $b['status'] === 'draft' ? 'Bounty published.' : 'Bounty updated.', 'success');
    header('Location: /OpenBounty/bounties/view.php?id='.$id); exit;
  }
}
$pageTitle = 'Edit bounty'; require __DIR__.'/../includes/header.php';
echo ob_page_head('Edit bounty', $b['title']); echo '<div style="max-width:820px">'; ob_bounty_form($v, $skills, $hasDiff, $errors, 'edit', $b); echo '</div>';
require __DIR__.'/../includes/footer.php';
