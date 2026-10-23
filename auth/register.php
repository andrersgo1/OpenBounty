<?php
require __DIR__.'/../includes/bootstrap.php';
if (current_user()) { header('Location: /OpenBounty/dashboard/'); exit; }

// There is no separate sign-up page: opening this URL shows the sign-up tab of the dialog.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  $q = ['auth'=>'register'];
  if (!empty($_GET['next'])) $q['next'] = safe_path((string)$_GET['next']);
  header('Location: /OpenBounty/?'.http_build_query($q));
  exit;
}

check_csrf();
$name    = trim((string)($_POST['full_name'] ?? ''));
$email   = strtolower(trim((string)($_POST['email'] ?? '')));
$pw      = (string)($_POST['password'] ?? '');
$confirm = (string)($_POST['confirm_password'] ?? '');
$next    = safe_path($_POST['next'] ?? '', '/OpenBounty/dashboard/');
$errors  = [];

if (mb_strlen($name) < 2 || mb_strlen($name) > 120) $errors[] = 'Name must be between 2 and 120 characters.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) $errors[] = 'Enter a valid email address.';
if (strlen($pw) < 10) $errors[] = 'Password must be at least 10 characters.';
if ($pw !== $confirm) $errors[] = 'Passwords do not match.';

if (!$errors) {
  try {
    $s = db()->prepare("INSERT INTO users(full_name,email,password_hash,role) VALUES(?,?,?,'member')");
    $s->execute([$name, $email, password_hash($pw, PASSWORD_DEFAULT)]);
    $id = (int)db()->lastInsertId();
    session_regenerate_id(true);
    $_SESSION['user'] = ['id'=>$id,'full_name'=>$name,'email'=>$email,'role'=>'member'];
    audit($id, 'register', 'user', $id);
    header('Location: '.$next);
    exit;
  } catch (PDOException $e) {
    if ($e->getCode() === '23000') $errors[] = 'That email is already registered.';
    else { $errors[] = 'Could not create your account. Check the database connection and schema.'; error_log($e->getMessage()); }
  }
}

$_SESSION['auth_error'] = ['mode'=>'register','messages'=>$errors,'email'=>$email,'full_name'=>$name,'next'=>$next];
header('Location: /OpenBounty/?auth=register');
exit;
