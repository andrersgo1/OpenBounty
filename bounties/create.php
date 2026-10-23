<?php
require __DIR__.'/../includes/bootstrap.php'; require __DIR__.'/../includes/bounty_form.php'; require_login(); $u = current_user(); $errors = [];
try { $skills = db()->query('SELECT id,name FROM skills ORDER BY name')->fetchAll(); } catch (Throwable $e) { $skills = []; }
$hasDiff = ob_col_exists('bounties', 'difficulty');
$v = ['title'=>'','description'=>'','acceptance_criteria'=>'','reward_xlm'=>'','deadline'=>'','skill_id'=>'','difficulty'=>''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  check_csrf();
  [$v, $errors] = ob_bounty_input($skills);
  $status = (($_POST['publish'] ?? 'draft') === 'publish') ? 'open' : 'draft';
  if (!$errors) {
    $dt = $v['deadline'] !== '' ? date('Y-m-d H:i:s', strtotime($v['deadline'])) : null;
    $cols = 'creator_id,title,description,acceptance_criteria,skill_id,reward_xlm,status,deadline';
    $vals = [(int)$u['id'], $v['title'], $v['description'], $v['acceptance_criteria'], $v['skill_id'] !== '' ? (int)$v['skill_id'] : null, number_format((float)$v['reward_xlm'], 7, '.', ''), $status, $dt];
    if ($hasDiff) { $cols .= ',difficulty'; $vals[] = $v['difficulty'] !== '' ? $v['difficulty'] : null; }
    $s = db()->prepare("INSERT INTO bounties($cols) VALUES(".implode(',', array_fill(0, count($vals), '?')).')'); $s->execute($vals);
    $id = (int)db()->lastInsertId(); audit((int)$u['id'], 'bounty_created', 'bounty', $id);
    flash($status === 'open' ? 'Bounty published.' : 'Bounty saved as draft.', 'success'); header('Location: /OpenBounty/bounties/view.php?id='.$id); exit;
  }
}
$pageTitle = 'Post a bounty'; require __DIR__.'/../includes/header.php';
echo ob_page_head('Post a bounty', 'Write clear acceptance criteria so contributors know what counts as done.');
echo '<div style="max-width:820px">'; ob_bounty_form($v, $skills, $hasDiff, $errors, 'create'); echo '</div>';
require __DIR__.'/../includes/footer.php';
