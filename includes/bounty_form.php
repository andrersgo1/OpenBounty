<?php
// Shared validation and form markup for creating and editing bounties.
function ob_bounty_input(array $skills): array {
  $v = ['title'=>trim((string)($_POST['title'] ?? '')),'description'=>trim((string)($_POST['description'] ?? '')),'acceptance_criteria'=>trim((string)($_POST['acceptance_criteria'] ?? '')),
        'reward_xlm'=>trim((string)($_POST['reward_xlm'] ?? '')),'deadline'=>trim((string)($_POST['deadline'] ?? '')),'skill_id'=>trim((string)($_POST['skill_id'] ?? '')),'difficulty'=>trim((string)($_POST['difficulty'] ?? ''))];
  $err = [];
  if (mb_strlen($v['title']) < 5 || mb_strlen($v['title']) > 180) $err[] = 'Title must be between 5 and 180 characters.';
  if (mb_strlen($v['description']) < 20) $err[] = 'Description must be at least 20 characters.';
  if (mb_strlen($v['description']) > 20000) $err[] = 'Description is too long.';
  if (mb_strlen($v['acceptance_criteria']) < 10) $err[] = 'Acceptance criteria must be at least 10 characters.';
  if (!is_numeric($v['reward_xlm']) || (float)$v['reward_xlm'] <= 0 || (float)$v['reward_xlm'] > 1000000) $err[] = 'Enter a valid reward greater than 0 and no more than 1,000,000 XLM.';
  if ($v['deadline'] !== '' && strtotime($v['deadline']) === false) $err[] = 'Enter a valid deadline.';
  if ($v['skill_id'] !== '' && (!ctype_digit($v['skill_id']) || !array_filter($skills, fn($s) => (string)$s['id'] === $v['skill_id']))) $err[] = 'Choose a valid skill.';
  if ($v['difficulty'] !== '' && !in_array($v['difficulty'], ['beginner','intermediate','advanced'], true)) $err[] = 'Choose a valid difficulty.';
  return [$v, $err];
}

function ob_bounty_form(array $v, array $skills, bool $hasDiff, array $errors, string $mode, ?array $bounty = null): void { ?>
<?php if ($errors): ?><div class="ob-flash error" role="alert"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" class="ob-card"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <label class="ob-l" for="f-title" style="margin-top:0">Title</label><input class="ob-in" id="f-title" name="title" maxlength="180" required value="<?= e($v['title']) ?>" placeholder="Fix API validation for empty inputs">
  <label class="ob-l" for="f-desc">Description</label><textarea class="ob-in" id="f-desc" name="description" rows="6" required placeholder="What needs to be built or fixed, and why it matters."><?= e($v['description']) ?></textarea>
  <label class="ob-l" for="f-crit">Acceptance criteria</label><textarea class="ob-in" id="f-crit" name="acceptance_criteria" rows="4" required placeholder="List the exact behavior or tests that must pass."><?= e($v['acceptance_criteria']) ?></textarea>
  <div class="ob-grid ob-g2">
    <div><label class="ob-l" for="f-skill">Language / skill</label><select class="ob-in" id="f-skill" name="skill_id"><option value="">General</option><?php foreach ($skills as $s): ?><option value="<?= (int)$s['id'] ?>"<?= (string)$v['skill_id'] === (string)$s['id'] ? ' selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
    <?php if ($hasDiff): ?><div><label class="ob-l" for="f-diff">Difficulty</label><select class="ob-in" id="f-diff" name="difficulty"><option value="">Not specified</option><option value="beginner"<?= $v['difficulty']==='beginner'?' selected':'' ?>>Beginner friendly</option><option value="intermediate"<?= $v['difficulty']==='intermediate'?' selected':'' ?>>Intermediate</option><option value="advanced"<?= $v['difficulty']==='advanced'?' selected':'' ?>>Advanced</option></select></div><?php endif; ?>
    <div><label class="ob-l" for="f-reward">Reward (XLM)</label><input class="ob-in" id="f-reward" name="reward_xlm" type="number" min="0.0000001" max="1000000" step="0.0000001" required value="<?= e($v['reward_xlm']) ?>"><p class="ob-hint">Stellar Testnet prototype. No real funds are moved.</p></div>
    <div><label class="ob-l" for="f-dl">Deadline (optional)</label><input class="ob-in" id="f-dl" name="deadline" type="datetime-local" value="<?= e($v['deadline']) ?>"></div>
  </div>
  <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:22px">
  <?php if ($mode === 'create'): ?>
    <button class="ob-btn" name="publish" value="publish" type="submit">Publish bounty</button><button class="ob-btn ghost" name="publish" value="draft" type="submit">Save draft</button>
  <?php else: ?>
    <button class="ob-btn" name="publish" value="save" type="submit">Save changes</button>
    <?php if ($bounty && $bounty['status'] === 'draft'): ?><button class="ob-btn good" name="publish" value="publish" type="submit">Save &amp; publish</button><?php endif; ?>
    <a class="ob-btn ghost" href="/OpenBounty/bounties/view.php?id=<?= (int)$bounty['id'] ?>">Cancel</a>
  <?php endif; ?>
  </div>
</form>
<?php }
