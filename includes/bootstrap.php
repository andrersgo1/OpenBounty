<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) {
  $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
  session_set_cookie_params(['httponly'=>true,'secure'=>$secure,'samesite'=>'Lax']);
  session_start();
}
$config = require __DIR__ . '/../config.php';
function db(): PDO {
  static $pdo = null;
  global $config;
  if ($pdo instanceof PDO) return $pdo;
  $dsn = 'mysql:host='.$config['db_host'].';port='.$config['db_port'].';dbname='.$config['db_name'].';charset=utf8mb4';
  $pdo = new PDO($dsn, $config['db_user'], $config['db_password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]);
  return $pdo;
}
function e(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function csrf_token(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function check_csrf(): void { $given = $_POST['csrf'] ?? ''; if (!is_string($given) || !hash_equals(csrf_token(), $given)) { http_response_code(419); exit('Session token expired. Go back, refresh the page, and try again.'); } }
function current_user(): ?array { return $_SESSION['user'] ?? null; }
function require_login(): void {
  if (current_user()) return;
  $q = ['auth'=>'login'];
  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') $q['next'] = (string)($_SERVER['REQUEST_URI'] ?? '');
  header('Location: /OpenBounty/?'.http_build_query($q));
  exit;
}
// Only allow redirects that stay inside this app (blocks open-redirect tricks).
function safe_path(?string $p, string $default = '/OpenBounty/dashboard/'): string {
  $p = (string)$p;
  if ($p === '' || strlen($p) > 500) return $default;
  if (!preg_match('#^/OpenBounty(?:/[^\s\\\\]*)?$#', $p) || str_contains($p, '//')) return $default;
  return $p;
}
// Reward amounts without the trailing zeros: 5.0000000 -> 5
function xlm($v): string {
  $s = number_format((float)$v, 7, '.', ',');
  return rtrim(rtrim($s, '0'), '.');
}
function audit(?int $actor, string $action, string $entity, ?int $id = null): void { try { $s=db()->prepare('INSERT INTO audit_logs(actor_user_id,action,entity_type,entity_id) VALUES(?,?,?,?)'); $s->execute([$actor,$action,$entity,$id]); } catch (Throwable $ignored) {} }
function flash(string $message, string $type='info'): void { $_SESSION['flash']=['message'=>$message,'type'=>$type]; }
function take_flash(): ?array { $f=$_SESSION['flash']??null; unset($_SESSION['flash']); return $f; }

require_once __DIR__ . "/ui.php";
