<?php
$pageTitle = $pageTitle ?? 'DevOrbit';
$u = current_user();
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (!function_exists('nav_active')) { function nav_active(string $prefix, string $path): string { return str_starts_with($path, $prefix) ? ' active' : ''; } }
$isPublicLanding = ($currentPath === '/OpenBounty/' || $currentPath === '/OpenBounty/index.php');
$userInitials = '';
if ($u) { $userInitials = strtoupper(substr($u['full_name'],0,1).(str_contains($u['full_name'],' ')?substr(strrchr($u['full_name'],' '),1,1):'')); }

// Sign-in dialog: show errors from a failed attempt (set by auth/login.php and auth/register.php).
$authErr = null;
if (!$u) { $authErr = $_SESSION['auth_error'] ?? null; unset($_SESSION['auth_error']); }
$authMode = (is_array($authErr) && ($authErr['mode'] ?? '') === 'register') ? 'register' : 'login';
if (!$authErr && ($_GET['auth'] ?? '') === 'register') $authMode = 'register';
$authMessages = is_array($authErr) ? (array)($authErr['messages'] ?? []) : [];
$authEmail = is_array($authErr) ? (string)($authErr['email'] ?? '') : '';
$authName  = is_array($authErr) ? (string)($authErr['full_name'] ?? '') : '';
$authNext  = is_array($authErr) ? safe_path((string)($authErr['next'] ?? ''), '') : '';
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="<?= $isPublicLanding ? '#0B1120' : '#0B1120' ?>"><title><?=e($pageTitle)?> · DevOrbit</title><link rel="icon" type="image/png" href="/OpenBounty/assets/favicon.png"><link rel="stylesheet" href="/OpenBounty/assets/backend.css"><meta name="description" content="Discover software bounties, contribute to open source, and track transparent rewards with DevOrbit.">
<?php if($isPublicLanding): ?><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter+Tight:wght@500;600;700;800&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500;600&display=swap"><link rel="stylesheet" href="/OpenBounty/assets/landing.css?v=<?= (int)@filemtime(__DIR__.'/../assets/landing.css') ?>"><?php else: ?><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500&display=swap"><link rel="stylesheet" href="/OpenBounty/assets/ob2.css?v=<?= (int)@filemtime(__DIR__.'/../assets/ob2.css') ?>"><?php endif; ?>
</head><body class="<?= $isPublicLanding ? 'is-landing' : 'ob-app' ?>">
<script>(function(){var t=(window.matchMedia&&matchMedia('(prefers-color-scheme: light)').matches)?'light':'dark';try{t=localStorage.getItem('openbounty-theme')||t}catch(e){}document.body.dataset.theme=t})();</script>
<?php if(!$u): ?>
<div class="auth-backdrop" id="auth-modal" aria-hidden="true">
  <div class="auth-dialog" role="dialog" aria-modal="true" aria-labelledby="auth-title">
    <button class="auth-close" type="button" data-close-auth aria-label="Close">×</button>
    <img class="auth-brand-mark" src="/OpenBounty/assets/devorbit-logo.jpg" alt="DevOrbit logo">
    <h2 id="auth-title"><?= $authMode==='register' ? 'Create your DevOrbit account' : 'Welcome back' ?></h2>
    <p class="muted auth-intro">Browsing is open to everyone. You only need an account to post a bounty, submit work, or review submissions.</p>
    <div class="auth-tabs"><button class="auth-tab<?= $authMode==='login'?' active':'' ?>" type="button" data-auth-tab="login">Log in</button><button class="auth-tab<?= $authMode==='register'?' active':'' ?>" type="button" data-auth-tab="register">Create account</button></div>
    <form class="auth-form" id="auth-login-form" method="post" action="/OpenBounty/auth/login.php"<?= $authMode==='login' ? '' : ' hidden' ?>>
      <?php if($authMode==='login' && $authMessages): ?><div class="flash error"><?php foreach($authMessages as $m): ?><div><?=e((string)$m)?></div><?php endforeach; ?></div><?php endif; ?>
      <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="next" value="<?=e($authNext)?>">
      <label for="am-email">Email</label><input id="am-email" name="email" type="email" required autocomplete="email" value="<?= $authMode==='login' ? e($authEmail) : '' ?>">
      <label for="am-password">Password</label><input id="am-password" name="password" type="password" required autocomplete="current-password">
      <button class="btn auth-submit" type="submit">Log in <span aria-hidden="true">→</span></button>
    </form>
    <form class="auth-form" id="auth-register-form" method="post" action="/OpenBounty/auth/register.php"<?= $authMode==='register' ? '' : ' hidden' ?>>
      <?php if($authMode==='register' && $authMessages): ?><div class="flash error"><?php foreach($authMessages as $m): ?><div><?=e((string)$m)?></div><?php endforeach; ?></div><?php endif; ?>
      <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="next" value="<?=e($authNext)?>">
      <label for="am-name">Full name</label><input id="am-name" name="full_name" required maxlength="120" autocomplete="name" value="<?=e($authName)?>">
      <label for="am-remail">Email</label><input id="am-remail" name="email" type="email" required maxlength="190" autocomplete="email" value="<?= $authMode==='register' ? e($authEmail) : '' ?>">
      <label for="am-rpassword">Password (at least 10 characters)</label><input id="am-rpassword" name="password" type="password" minlength="10" required autocomplete="new-password">
      <label for="am-rconfirm">Confirm password</label><input id="am-rconfirm" name="confirm_password" type="password" minlength="10" required autocomplete="new-password">
      <button class="btn auth-submit" type="submit">Create account <span aria-hidden="true">→</span></button>
    </form>
    <p class="auth-switch"><span><?= $authMode==='register' ? 'Already have an account?' : 'New here?' ?></span> <button type="button" data-auth-tab="<?= $authMode==='register' ? 'login' : 'register' ?>"><?= $authMode==='register' ? 'Log in' : 'Create an account' ?></button></p>
    <p class="auth-legal">DevOrbit is a Stellar testnet prototype. No real funds are moved or promised.</p>
  </div>
</div>
<?php endif; ?>
<?php if($isPublicLanding): ?>
<div class="lp">
<div class="lp-bg" aria-hidden="true"></div>
<header class="lp-nav"><div class="lp-wrap lp-nav-in">
  <a class="lp-brand" href="/OpenBounty/"><img class="lp-logo" src="/OpenBounty/assets/devorbit-logo.jpg" alt="DevOrbit logo">DevOrbit</a>
  <nav class="lp-links" aria-label="Page sections"><a href="/OpenBounty/bounties/">Explore</a><a href="#feed">Bounty feed</a><a href="#how">How it works</a><a href="#creators">For creators</a><a href="#contributors">For contributors</a></nav>
  <div class="lp-actions">
    <button type="button" class="lp-icon-btn" id="theme-toggle" data-icon-only aria-label="Toggle color theme"><svg class="i-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 14.5A8 8 0 0 1 9.5 4 8 8 0 1 0 20 14.5Z"/></svg><svg class="i-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg></button>
    <?php if($u): ?>
      <a class="lp-btn lp-btn-text" href="/OpenBounty/dashboard/">Dashboard</a>
      <form method="post" action="/OpenBounty/auth/logout.php"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><button class="lp-btn lp-btn-text" type="submit">Log out</button></form>
      <a class="lp-btn lp-btn-primary" href="/OpenBounty/bounties/create.php">Post a bounty</a>
    <?php else: ?>
      <a class="lp-btn lp-btn-text" href="/OpenBounty/?auth=login" data-open-auth="login">Log in</a>
      <a class="lp-btn lp-btn-primary" href="/OpenBounty/bounties/create.php" data-auth-required="1" data-auth-mode="register">Post a bounty</a>
    <?php endif; ?>
  </div>
</div></header>
<main class="lp-main">
<?php if($f=take_flash()): ?><div class="lp-wrap" style="padding-top:18px"><div class="flash <?=e($f['type'])?>"><?=e($f['message'])?></div></div><?php endif; ?>
<?php else:
$ob_unread = 0; $ob_walletAddr = '';
if ($u) { $ob_walletAddr = (string)(ob_user_row((int)$u['id'])['wallet_address'] ?? ''); foreach (ob_notifications((int)$u['id'], 30) as $ob_n) { if (empty($ob_n['read'])) $ob_unread++; } }
$ob_navKey = '';
foreach ([['/OpenBounty/bounties/mine.php','mine'],['/OpenBounty/bounties/','explore'],['/OpenBounty/developers','dev'],['/OpenBounty/profile','dev'],['/OpenBounty/leaderboard','lead'],['/OpenBounty/dashboard','dash'],['/OpenBounty/submissions','subs'],['/OpenBounty/reviews','rev'],['/OpenBounty/wallet','wallet'],['/OpenBounty/payments','pay'],['/OpenBounty/transactions','tx'],['/OpenBounty/skillmentor','mentor'],['/OpenBounty/notifications','notif'],['/OpenBounty/settings','settings']] as $ob_m) { if (str_starts_with($currentPath, $ob_m[0])) { $ob_navKey = $ob_m[1]; break; } }
$ob_home = $u ? '/OpenBounty/dashboard/' : '/OpenBounty/';
if ($u && $ob_navKey === 'dash') $ob_navKey = 'home';
$ob_navGroups = [
  'Discover' => [['home','home','Home',$ob_home],['explore','compass','Explore Bounties','/OpenBounty/bounties/'],['dev','users','Developers','/OpenBounty/developers/'],['lead','trophy','Leaderboard','/OpenBounty/leaderboard/']],
  'Workspace' => [['mine','flag','My Bounties','/OpenBounty/bounties/mine.php'],['subs','upload','My Submissions','/OpenBounty/submissions/'],['rev','check','Reviews','/OpenBounty/reviews/']],
  'Finance' => [['wallet','wallet','Wallet','/OpenBounty/wallet/'],['pay','card','Payments','/OpenBounty/payments/'],['tx','list','Transaction History','/OpenBounty/transactions/']],
  'Tools' => [['mentor','spark','SkillMentor','/OpenBounty/skillmentor/'],['notif','bell','Notifications','/OpenBounty/notifications/'],['settings','sliders','Settings','/OpenBounty/settings/']],
];
?>
<a class="ob-skip" href="#main">Skip to content</a>
<div class="ob-shell">
<div class="sidebar-backdrop" id="sidebar-backdrop" hidden></div>
<aside class="ob-side" id="site-sidebar" aria-label="Main navigation">
  <a class="ob-brand" href="<?= e($ob_home) ?>" data-tip="DevOrbit"><img class="ob-logo" src="/OpenBounty/assets/devorbit-logo.jpg" alt="DevOrbit logo"><span class="ob-brand-t">DevOrbit<small>Build · Contribute · Earn</small></span></a>
  <nav class="ob-nav">
  <?php foreach ($ob_navGroups as $ob_label => $ob_items): ?>
    <div class="ob-nav-g"><div class="ob-nav-label"><?= e($ob_label) ?></div>
    <?php foreach ($ob_items as [$ob_key, $ob_icon, $ob_text, $ob_href]): ?>
      <a class="ob-nav-i<?= $ob_navKey === $ob_key ? ' active' : '' ?>" href="<?= e($ob_href) ?>" data-tip="<?= e($ob_text) ?>"<?= $ob_navKey === $ob_key ? ' aria-current="page"' : '' ?>><?= ob_icon($ob_icon, 19) ?><span class="ob-nav-t"><?= e($ob_text) ?></span><?php if ($ob_key === 'notif' && $ob_unread > 0): ?><b class="ob-count"><?= (int)$ob_unread ?></b><?php endif; ?></a>
    <?php endforeach; ?></div>
  <?php endforeach; ?>
  </nav>
  <div class="ob-net"><span class="live-dot"></span><div><strong>Stellar Testnet</strong><small>Prototype environment</small></div></div>
</aside>
<div class="ob-main">
<header class="ob-top">
  <button type="button" class="ob-icon-btn ob-only-mobile" id="mobile-menu" aria-label="Open navigation" aria-controls="site-sidebar" aria-expanded="false"><?= ob_icon('menu') ?></button>
  <button type="button" class="ob-icon-btn ob-only-desktop" id="sb-collapse" aria-label="Collapse sidebar" aria-pressed="false"><?= ob_icon('chev') ?></button>
  <form class="ob-gsearch" method="get" action="/OpenBounty/bounties/" role="search"><?= ob_icon('search', 16) ?><input type="search" name="q" placeholder="Search bounties, skills…" aria-label="Search bounties" maxlength="100"></form>
  <div class="ob-top-r">
    <?php if ($u): ?>
      <a class="ob-wallet-pill<?= $ob_walletAddr !== '' ? ' on' : '' ?>" href="/OpenBounty/wallet/" title="<?= $ob_walletAddr !== '' ? 'Wallet address saved. Not a live wallet connection.' : 'No wallet address saved yet' ?>"><i></i><span><?= $ob_walletAddr !== '' ? 'Testnet · '.e(ob_short($ob_walletAddr, 4)) : 'No wallet' ?></span></a>
    <?php endif; ?>
    <button type="button" class="ob-icon-btn" id="theme-toggle" data-icon-only aria-label="Toggle color theme"><span class="i-moon"><?= ob_icon('moon') ?></span><span class="i-sun"><?= ob_icon('sun') ?></span></button>
    <?php if ($u): ?>
      <a class="ob-icon-btn ob-bell" href="/OpenBounty/notifications/" aria-label="Notifications<?= $ob_unread ? ' ('.(int)$ob_unread.' unread)' : '' ?>"><?= ob_icon('bell') ?><?php if ($ob_unread > 0): ?><b><?= $ob_unread > 9 ? '9+' : (int)$ob_unread ?></b><?php endif; ?></a>
      <details class="ob-menu"><summary aria-label="Profile menu"><span class="ob-avatar"><?= e($userInitials) ?></span><span class="ob-menu-n"><?= e($u['full_name']) ?></span></summary>
        <div class="ob-menu-p"><div class="ob-menu-h"><strong><?= e($u['full_name']) ?></strong><small><?= e($u['email']) ?> · <?= e($u['role']) ?></small></div>
          <a href="/OpenBounty/profile/"><?= ob_icon('user', 16) ?>My portfolio</a><a href="/OpenBounty/settings/"><?= ob_icon('sliders', 16) ?>Settings</a><a href="/OpenBounty/wallet/"><?= ob_icon('wallet', 16) ?>Wallet</a>
          <form method="post" action="/OpenBounty/auth/logout.php"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><button type="submit"><?= ob_icon('logout', 16) ?>Log out</button></form></div></details>
      <a class="ob-btn sm ob-hide-sm" href="/OpenBounty/bounties/create.php"><?= ob_icon('plus', 15) ?>Post a bounty</a>
    <?php else: ?>
      <a class="ob-btn ghost sm" href="/OpenBounty/?auth=login" data-open-auth="login">Log in</a><a class="ob-btn sm" href="/OpenBounty/?auth=register" data-open-auth="register">Get started</a>
    <?php endif; ?>
  </div>
</header>
<main class="ob-content" id="main"><h1 class="ob-sr"><?= e($pageTitle) ?></h1>
<?php if ($f = take_flash()): ?><div class="ob-flash <?= e($f['type']) ?>" role="status"><?= e($f['message']) ?></div><?php endif; ?>
<?php endif; ?>
