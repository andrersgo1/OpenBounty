<?php
require __DIR__.'/../includes/bootstrap.php';
if (current_user()) { header('Location: /OpenBounty/dashboard/'); exit; }

// There is no separate login page: opening this URL shows the sign-in dialog instead.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  $q = ['auth'=>'login'];
  if (!empty($_GET['next'])) $q['next'] = safe_path((string)$_GET['next']);
  header('Location: /OpenBounty/?'.http_build_query($q));
  exit;
}

check_csrf();
$email = strtolower(trim((string)($_POST['email'] ?? '')));
$pw    = (string)($_POST['password'] ?? '');
$next  = safe_path($_POST['next'] ?? '', '/OpenBounty/dashboard/');
$error = '';

try {
  $s = db()->prepare('SELECT id,full_name,email,password_hash,role FROM users WHERE email=? LIMIT 1');
  $s->execute([$email]);
  $u = $s->fetch();
  if ($u && password_verify($pw, $u['password_hash'])) {
    session_regenerate_id(true);
    $_SESSION['user'] = ['id'=>(int)$u['id'],'full_name'=>$u['full_name'],'email'=>$u['email'],'role'=>$u['role']];
    audit((int)$u['id'], 'login', 'user', (int)$u['id']);
    header('Location: '.$next);
    exit;
  }
  $error = 'Email or password is incorrect.';
} catch (Throwable $e) {
  $error = 'Could not connect to the database. Confirm that MySQL is running and schema.sql has been imported.';
  error_log($e->getMessage());
}

// Show the error inside the dialog on the page the person came from.
$_SESSION['auth_error'] = ['mode'=>'login','messages'=>[$error],'email'=>$email,'next'=>$next];
header('Location: /OpenBounty/?auth=login');
exit;
