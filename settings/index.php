<?php
require __DIR__.'/../includes/bootstrap.php'; require_login(); $u = current_user(); $uid = (int)$u['id'];
$hasProf = ob_table_exists('user_profiles'); $errors = []; $section = '';
$allSkills = []; try { $allSkills = db()->query('SELECT id,name FROM skills ORDER BY name')->fetchAll(); } catch (Throwable $e) {}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  check_csrf(); $act = (string)($_POST['action'] ?? ''); $section = $act;
  try {
    if ($act === 'profile') {
      $name = trim((string)($_POST['full_name'] ?? '')); $bio = trim((string)($_POST['bio'] ?? '')); $head = trim((string)($_POST['headline'] ?? '')); $gh = trim((string)($_POST['github_url'] ?? '')); $web = trim((string)($_POST['website_url'] ?? '')); $pub = isset($_POST['is_public']) ? 1 : 0;
      if (mb_strlen($name) < 2 || mb_strlen($name) > 120) $errors[] = 'Name must be between 2 and 120 characters.';
      if (mb_strlen($bio) > 2000) $errors[] = 'Bio must be 2,000 characters or fewer.';
      if (mb_strlen($head) > 160) $errors[] = 'Headline must be 160 characters or fewer.';
      if ($gh !== '' && !preg_match('#^https://(www\.)?github\.com/[A-Za-z0-9_.-]+/?#i', $gh)) $errors[] = 'GitHub link must start with https://github.com/.';
      if ($web !== '' && ob_safe_url($web) === '') $errors[] = 'Website must be a valid http(s) URL.';
      if (strlen($gh) > 255 || strlen($web) > 255) $errors[] = 'Links must be 255 characters or fewer.';
      if (!$errors) {
        db()->prepare('UPDATE users SET full_name=?,bio=? WHERE id=?')->execute([$name, $bio !== '' ? $bio : null, $uid]); $_SESSION['user']['full_name'] = $name;
        if ($hasProf) db()->prepare('INSERT INTO user_profiles(user_id,headline,github_url,website_url,is_public) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE headline=VALUES(headline),github_url=VALUES(github_url),website_url=VALUES(website_url),is_public=VALUES(is_public)')->execute([$uid, $head !== '' ? $head : null, $gh !== '' ? $gh : null, $web !== '' ? $web : null, $pub]);
        audit($uid, 'profile_updated', 'user', $uid); flash('Profile saved.', 'success'); header('Location: /OpenBounty/settings/'); exit;
      }
    } elseif ($act === 'skills') {
      $valid = array_column($allSkills, 'id'); $chosen = []; foreach ((array)($_POST['skills'] ?? []) as $sid) { if (ctype_digit((string)$sid) && in_array((int)$sid, array_map('intval', $valid), true)) $chosen[(int)$sid] = in_array($_POST['prof'][$sid] ?? '', ['beginner','intermediate','advanced'], true) ? $_POST['prof'][$sid] : 'beginner'; }
      if (count($chosen) > 20) $errors[] = 'Choose at most 20 skills.';
      else { $pdo = db(); $pdo->beginTransaction(); try { $pdo->prepare('DELETE FROM user_skills WHERE user_id=?')->execute([$uid]); $ins = $pdo->prepare('INSERT INTO user_skills(user_id,skill_id,proficiency) VALUES(?,?,?)'); foreach ($chosen as $sid => $pf) $ins->execute([$uid, $sid, $pf]); $pdo->commit(); flash('Skills saved.', 'success'); header('Location: /OpenBounty/settings/#skills'); exit; } catch (Throwable $e) { $pdo->rollBack(); throw $e; } }
    } elseif ($act === 'password') {
      $cur = (string)($_POST['current_password'] ?? ''); $new = (string)($_POST['new_password'] ?? ''); $conf = (string)($_POST['confirm_password'] ?? '');
      $s = db()->prepare('SELECT password_hash FROM users WHERE id=?'); $s->execute([$uid]); $hash = (string)$s->fetchColumn();
      if (!password_verify($cur, $hash)) $errors[] = 'Your current password is incorrect.';
      if (strlen($new) < 10) $errors[] = 'New password must be at least 10 characters.';
      if ($new !== $conf) $errors[] = 'New password and confirmation do not match.';
      if (!$errors) { db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($new, PASSWORD_DEFAULT), $uid]); session_regenerate_id(true); audit($uid, 'password_changed', 'user', $uid); flash('Password updated.', 'success'); header('Location: /OpenBounty/settings/#account'); exit; }
    }
  } catch (Throwable $e) { error_log($e->getMessage()); $errors[] = 'Something went wrong while saving. Please try again.'; }
}
$me = ob_user_row($uid); $prof = ['headline'=>'','github_url'=>'','website_url'=>'','is_public'=>1];
if ($hasProf) { $s = db()->prepare('SELECT headline,github_url,website_url,is_public FROM user_profiles WHERE user_id=?'); $s->execute([$uid]); $prof = $s->fetch() ?: $prof; }
$mySkills = []; try { $s = db()->prepare('SELECT skill_id,proficiency FROM user_skills WHERE user_id=?'); $s->execute([$uid]); $mySkills = array_column($s->fetchAll(), 'proficiency', 'skill_id'); } catch (Throwable $e) {}
$pv = fn($k, $d = '') => $section === 'profile' ? (string)($_POST[$k] ?? $d) : $d;
$pageTitle = 'Settings'; require __DIR__.'/../includes/header.php';
echo ob_page_head('Settings', 'Manage your profile, skills and account.');
if (!$hasProf) echo ob_migration_notice();
if ($errors): ?><div class="ob-flash error" role="alert"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="ob-split"><div class="ob-stack">
<form method="post" class="ob-card" id="profile"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="profile"><h3>Profile</h3>
 <label class="ob-l" for="s-name">Full name</label><input class="ob-in" id="s-name" name="full_name" required maxlength="120" value="<?= e($pv('full_name', $me['full_name'] ?? '')) ?>">
 <?php if ($hasProf): ?><label class="ob-l" for="s-head">Headline</label><input class="ob-in" id="s-head" name="headline" maxlength="160" value="<?= e($pv('headline', (string)$prof['headline'])) ?>" placeholder="Full-stack developer · PHP &amp; React"><?php endif; ?>
 <label class="ob-l" for="s-bio">Bio</label><textarea class="ob-in" id="s-bio" name="bio" rows="4" maxlength="2000"><?= e($pv('bio', (string)($me['bio'] ?? ''))) ?></textarea>
 <?php if ($hasProf): ?><div class="ob-grid ob-g2"><div><label class="ob-l" for="s-gh">GitHub</label><input class="ob-in" id="s-gh" name="github_url" type="url" maxlength="255" value="<?= e($pv('github_url', (string)$prof['github_url'])) ?>" placeholder="https://github.com/you"></div><div><label class="ob-l" for="s-web">Website</label><input class="ob-in" id="s-web" name="website_url" type="url" maxlength="255" value="<?= e($pv('website_url', (string)$prof['website_url'])) ?>" placeholder="https://"></div></div>
 <label style="display:flex;gap:10px;align-items:flex-start;margin-top:16px"><input type="checkbox" name="is_public" value="1" <?= ($section === 'profile' ? isset($_POST['is_public']) : (int)$prof['is_public'] === 1) ? 'checked' : '' ?>><span><strong>Public profile</strong><br><span class="ob-hint">Lists you in the developer directory and leaderboard. Your email and wallet address are never shown publicly.</span></span></label><?php endif; ?>
 <div style="margin-top:18px"><button class="ob-btn" type="submit">Save profile</button> <a class="ob-btn ghost" href="/OpenBounty/profile/">View portfolio</a></div></form>

<form method="post" class="ob-card" id="skills"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="skills"><h3>Skills</h3><p class="ob-muted" style="margin:4px 0 12px">Used for recommendations, your portfolio and the developer directory.</p>
 <?php if (!$allSkills): ?><p class="ob-muted">No skills exist yet. Run the 2.0 migration to add a starter catalog.</p><?php else: ?><div class="ob-grid" style="grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:10px"><?php foreach ($allSkills as $sk): $has = isset($mySkills[$sk['id']]); ?><div style="display:flex;gap:8px;align-items:center;border:1px solid var(--line);border-radius:10px;padding:8px 10px"><label style="display:flex;gap:8px;align-items:center;flex:1"><input type="checkbox" name="skills[]" value="<?= (int)$sk['id'] ?>" <?= $has ? 'checked' : '' ?>><?= e($sk['name']) ?></label><select class="ob-in" name="prof[<?= (int)$sk['id'] ?>]" style="width:auto;padding:4px 6px;font-size:12px" aria-label="Level for <?= e($sk['name']) ?>"><?php foreach (['beginner','intermediate','advanced'] as $lv): ?><option value="<?= $lv ?>"<?= ($mySkills[$sk['id']] ?? 'beginner') === $lv ? ' selected' : '' ?>><?= ucfirst($lv) ?></option><?php endforeach; ?></select></div><?php endforeach; ?></div><div style="margin-top:16px"><button class="ob-btn" type="submit">Save skills</button></div><?php endif; ?></form></div>

<aside class="ob-stack"><section class="ob-card" id="account"><h3>Account</h3><dl class="ob-kv" style="margin-top:10px"><dt>Email</dt><dd><?= e($me['email'] ?? $u['email']) ?></dd><dt>Role</dt><dd><?= e($u['role']) ?></dd></dl>
 <form method="post" style="margin-top:14px"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="password"><strong>Change password</strong>
 <label class="ob-l" for="pw0">Current password</label><input class="ob-in" id="pw0" name="current_password" type="password" autocomplete="current-password" required>
 <label class="ob-l" for="pw1">New password (10+ characters)</label><input class="ob-in" id="pw1" name="new_password" type="password" minlength="10" autocomplete="new-password" required>
 <label class="ob-l" for="pw2">Confirm new password</label><input class="ob-in" id="pw2" name="confirm_password" type="password" minlength="10" autocomplete="new-password" required>
 <div style="margin-top:14px"><button class="ob-btn ghost" type="submit">Update password</button></div></form></section>
<section class="ob-card"><h3>Preferences</h3><p class="ob-muted" style="margin:6px 0 12px">Theme is stored in this browser only.</p><button class="ob-btn ghost" type="button" onclick="document.getElementById('theme-toggle').click()">Toggle light / dark</button></section>
<section class="ob-card"><h3>Session</h3><form method="post" action="/OpenBounty/auth/logout.php" style="margin-top:10px"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><button class="ob-btn danger" type="submit"><?= ob_icon('logout', 16) ?>Log out</button></form></section></aside></div>
<?php require __DIR__.'/../includes/footer.php'; ?>
