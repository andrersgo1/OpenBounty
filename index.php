<?php
require __DIR__.'/includes/bootstrap.php';
require_once __DIR__.'/includes/demo-data.php';
$u = current_user();
$pageTitle = 'Open-source bounty board';

// ---- Feed filters (all optional, all validated) ----
$publicStatuses = "'open','in_review','approved','payment_pending','paid'";
$statusFilter = (string)($_GET['status'] ?? 'all');
if (!in_array($statusFilter, ['all','open','in_review','completed'], true)) $statusFilter = 'all';
$skillFilter = (isset($_GET['skill']) && ctype_digit((string)$_GET['skill'])) ? (int)$_GET['skill'] : 0;
$sortFilter = (($_GET['sort'] ?? '') === 'reward') ? 'reward' : 'newest';
$isFiltered = ($statusFilter !== 'all' || $skillFilter > 0 || $sortFilter !== 'newest');

$stats = ['total'=>0, 'open'=>0, 'open_xlm'=>'0'];
$featured = [];
$feed = [];
$feedCount = 0;
$skills = [];
try {
  $pdo = db();

  $row = $pdo->query("SELECT COUNT(*) AS total, COALESCE(SUM(b.status='open'),0) AS open_n, COALESCE(SUM(CASE WHEN b.status='open' THEN b.reward_xlm ELSE 0 END),0) AS open_xlm FROM bounties b WHERE b.status IN ($publicStatuses)")->fetch();
  if ($row) { $stats = ['total'=>(int)$row['total'], 'open'=>(int)$row['open_n'], 'open_xlm'=>(string)$row['open_xlm']]; }

  $featured = $pdo->query("SELECT b.id,b.title,b.description,b.reward_xlm,b.status,u.full_name AS creator,s.name AS skill FROM bounties b JOIN users u ON u.id=b.creator_id LEFT JOIN skills s ON s.id=b.skill_id WHERE b.status IN ($publicStatuses) ORDER BY FIELD(b.status,'open','in_review','approved','payment_pending','paid'), b.created_at DESC LIMIT 3")->fetchAll();

  $where = ["b.status IN ($publicStatuses)"];
  $args = [];
  if ($statusFilter === 'open') $where[] = "b.status='open'";
  elseif ($statusFilter === 'in_review') $where[] = "b.status='in_review'";
  elseif ($statusFilter === 'completed') $where[] = "b.status IN ('approved','payment_pending','paid')";
  if ($skillFilter > 0) { $where[] = 'b.skill_id = ?'; $args[] = $skillFilter; }
  $whereSql = implode(' AND ', $where);
  $orderSql = ($sortFilter === 'reward') ? 'b.reward_xlm DESC, b.created_at DESC' : 'b.created_at DESC';

  $cs = $pdo->prepare("SELECT COUNT(*) FROM bounties b WHERE $whereSql");
  $cs->execute($args);
  $feedCount = (int)$cs->fetchColumn();

  $fs = $pdo->prepare("SELECT b.id,b.title,b.description,b.reward_xlm,b.status,u.full_name AS creator,s.name AS skill FROM bounties b JOIN users u ON u.id=b.creator_id LEFT JOIN skills s ON s.id=b.skill_id WHERE $whereSql ORDER BY $orderSql LIMIT 12");
  $fs->execute($args);
  $feed = $fs->fetchAll();

  $skills = $pdo->query('SELECT id,name FROM skills ORDER BY name')->fetchAll();
} catch (Throwable $e) {
  // Keep the public page readable if the database is unavailable.
  error_log($e->getMessage());
}

$statusLabel = ['open'=>'Open','in_review'=>'In review','approved'=>'Approved','payment_pending'=>'Payment pending','paid'=>'Paid'];
$statusTone  = ['open'=>'green','in_review'=>'amber','approved'=>'','payment_pending'=>'amber','paid'=>'green'];
$symbol = static function (array $b): string {
  $skill = trim((string)($b['skill'] ?? ''));
  return $skill !== '' ? strtoupper(mb_substr($skill, 0, 2)) : 'DO';
};
$postAttrs = $u ? '' : ' data-auth-required="1" data-auth-mode="register"';

require __DIR__.'/includes/header.php';
?>
<div class="lp-wrap">

  <!-- Hero: your original headline and feed card, kept -->
  <section class="lp-hero" aria-labelledby="hero-title">
    <div>
      <p class="lp-eyebrow line">Open-source bounty board</p>
      <h1 id="hero-title">Your issues, claimed.<span class="lp-grad">Rewards, earned.</span></h1>
      <p class="lp-lede">Discover software tasks, review the requirements, and find a bounty that matches your skills. Browse freely; sign in when you are ready to participate.</p>
      <div class="lp-hero-actions">
        <a class="lp-btn lp-btn-primary lp-btn-lg" href="/OpenBounty/bounties/">Explore Bounties <span aria-hidden="true">→</span></a>
        <a class="lp-btn lp-btn-ghost lp-btn-lg" href="/OpenBounty/bounties/create.php"<?=$postAttrs?>>Post a Bounty</a>
      </div>
      <div class="lp-ticks"><span>Public bounty listings</span><span>Rewards shown clearly</span><span>Sign in to submit</span></div>
    </div>

    <aside class="lp-card" aria-label="Latest bounties">
      <div class="lp-card-head">
        <div><p class="lp-eyebrow"><span class="lp-live"></span>Bounty feed</p><h3>Open bounties</h3></div>
        <a class="lp-viewall" href="/OpenBounty/bounties/">View all ↗</a>
      </div>
      <div class="lp-sum">
        <div><b><?=number_format($stats['total'])?></b><span>public bounties</span></div>
        <div><b><?=number_format($stats['open'])?></b><span>open now</span></div>
        <div><b><?=e(xlm($stats['open_xlm']))?></b><span>XLM available</span></div>
      </div>
      <?php if($featured): foreach($featured as $b): ?>
        <a class="lp-mini" href="/OpenBounty/bounties/view.php?id=<?=(int)$b['id']?>">
          <span class="lp-sym"><?=e($symbol($b))?></span>
          <span class="lp-mini-main"><b><?=e($b['title'])?></b><span><?=e((string)($b['skill'] ?? 'General'))?> · <?=e($statusLabel[$b['status']] ?? str_replace('_',' ',$b['status']))?></span></span>
          <span class="lp-reward"><?=e(xlm($b['reward_xlm']))?><small>XLM</small></span>
        </a>
      <?php endforeach; else: ?>
        <div class="lp-card-empty"><b>No public bounties yet.</b>Published bounties will appear here for everyone to browse.</div>
      <?php endif; ?>
      <div class="lp-card-foot"><span>Browse without an account</span><span>Sign in to take action</span></div>
    </aside>
  </section>

  <!-- Bounty feed -->
  <section class="lp-section" id="feed" aria-labelledby="feed-title">
    <div class="lp-section-head">
      <p class="lp-eyebrow">Public feed</p>
      <h2 id="feed-title">Open bounties</h2>
      <p>Browse without an account. Sign in to submit work.</p>
    </div>
    <form class="lp-filters" method="get" action="/OpenBounty/#feed">
      <div><label for="f-status">Status</label>
        <select id="f-status" name="status">
          <option value="all"<?=$statusFilter==='all'?' selected':''?>>All statuses</option>
          <option value="open"<?=$statusFilter==='open'?' selected':''?>>Open</option>
          <option value="in_review"<?=$statusFilter==='in_review'?' selected':''?>>In review</option>
          <option value="completed"<?=$statusFilter==='completed'?' selected':''?>>Approved or paid</option>
        </select></div>
      <div><label for="f-skill">Skill</label>
        <select id="f-skill" name="skill">
          <option value="0">Any skill</option>
          <?php foreach($skills as $s): ?><option value="<?=(int)$s['id']?>"<?=$skillFilter===(int)$s['id']?' selected':''?>><?=e($s['name'])?></option><?php endforeach; ?>
        </select></div>
      <div><label for="f-sort">Sort</label>
        <select id="f-sort" name="sort">
          <option value="newest"<?=$sortFilter==='newest'?' selected':''?>>Newest</option>
          <option value="reward"<?=$sortFilter==='reward'?' selected':''?>>Highest reward</option>
        </select></div>
      <button class="lp-btn lp-btn-ghost" type="submit">Apply</button>
    </form>
    <p class="lp-count"><?=number_format($feedCount)?> <?=$feedCount===1?'bounty matches':'bounties match'?> <?=$isFiltered?'your filters':'right now'?>.</p>

    <div class="lp-list">
      <?php if($feed): foreach($feed as $b): ?>
        <a class="lp-row" href="/OpenBounty/bounties/view.php?id=<?=(int)$b['id']?>">
          <span class="lp-sym"><?=e($symbol($b))?></span>
          <span class="lp-row-main">
            <span class="lp-row-meta"><span class="lp-chip"><?=e((string)($b['skill'] ?? 'General'))?></span><span class="lp-chip <?=e($statusTone[$b['status']] ?? '')?>"><?=e($statusLabel[$b['status']] ?? str_replace('_',' ',$b['status']))?></span></span>
            <h3><?=e($b['title'])?></h3>
            <p><?=e(mb_strimwidth((string)$b['description'], 0, 170, '…'))?></p>
            <span class="lp-by" style="display:block">Posted by <b><?=e($b['creator'])?></b></span>
          </span>
          <span class="lp-row-end"><span class="lp-reward"><?=e(xlm($b['reward_xlm']))?><small>XLM</small></span><span>View details →</span></span>
        </a>
      <?php endforeach; else: ?>
        <div class="lp-empty">
          <?php if($isFiltered): ?><b>No bounties match those filters.</b><a href="/OpenBounty/#feed">Clear filters →</a>
          <?php else: ?><b>The board is ready for its first bounty.</b><span>Be the first to share a useful software task with the community.</span><a href="/OpenBounty/bounties/create.php"<?=$postAttrs?>>Post the first bounty →</a><?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
    <?php if($feedCount > count($feed)): ?><div class="lp-more"><a class="lp-btn lp-btn-ghost" href="/OpenBounty/bounties/">See all <?=number_format($feedCount)?> bounties →</a></div><?php endif; ?>
  </section>

  <!-- How it works -->
  <section class="lp-section" id="how" aria-labelledby="how-title">
    <div class="lp-section-head center">
      <p class="lp-eyebrow">How it works</p>
      <h2 id="how-title">Post. Submit. Review.</h2>
      <p>Every bounty says what “done” means before anyone starts, and every decision is recorded.</p>
    </div>
    <div class="lp-steps">
      <div class="lp-step"><i>1</i><h3>Creator posts a bounty</h3><p>Describe the task, set a reward in XLM, and write the acceptance criteria. Save a draft first, or publish straight to the board.</p></div>
      <div class="lp-step"><i>2</i><h3>Contributor submits work</h3><p>Anyone with an account can send a pull request or evidence link, plus notes. One submission per person, and never on your own bounty.</p></div>
      <div class="lp-step"><i>3</i><h3>Review decides</h3><p>The creator or a reviewer approves, requests changes, or rejects, with notes the contributor can read.</p><p>Approval does not mark a bounty paid. Payment proof appears once Paiflow payouts are connected.</p></div>
    </div>
  </section>

  <!-- For creators -->
  <section class="lp-section" id="creators" aria-labelledby="creators-title">
    <div class="lp-split">
      <div>
        <p class="lp-eyebrow">For bounty creators</p>
        <h2 id="creators-title">Pay people who fix your issues.</h2>
        <p>Turn a task into a clear bounty and let the right contributor find it. You stay in control of what gets accepted.</p>
        <ul class="lp-checks">
          <li>Acceptance criteria that spell out exactly what counts as done</li>
          <li>Reward in XLM with an optional deadline and skill tag</li>
          <li>Drafts stay private until you publish</li>
          <li>Approve, request changes, or reject each submission with notes</li>
        </ul>
        <div class="lp-hero-actions"><a class="lp-btn lp-btn-primary" href="/OpenBounty/bounties/create.php"<?=$postAttrs?>>Post a bounty</a></div>
      </div>
      <div class="lp-code-col">
        <div class="lp-code" role="img" aria-label="Example of a bounty and its review">
          <div><span class="c"># New bounty</span></div>
          <div><span class="k">title</span>       <span class="v">Fix API validation for empty inputs</span></div>
          <div><span class="k">reward</span>      <span class="ok">12 XLM</span></div>
          <div><span class="k">skill</span>       <span class="v">PHP</span></div>
          <div><span class="k">deadline</span>    <span class="v">2026-12-15</span></div>
          <div><span class="c">↳ done when: empty body returns 422, tests pass</span></div>
          <div class="gap"></div>
          <div><span class="c"># After a submission</span></div>
          <div><span class="ok">✓</span> <span class="v">Approved by creator</span></div>
          <div><span class="c">↳ payment proof shows here once verified</span></div>
        </div>
        <p class="lp-code-note">Example for illustration only, not a real listing.</p>
      </div>
    </div>
  </section>

  <!-- For contributors -->
  <section class="lp-section" id="contributors" aria-labelledby="contributors-title">
    <div class="lp-split">
      <div class="lp-code-col">
        <div class="lp-code" role="img" aria-label="Example of a submission and its review">
          <div><span class="c"># Submit work</span></div>
          <div><span class="k">link</span>   <span class="v">github.com/you/project/pull/123</span></div>
          <div><span class="k">notes</span>  <span class="v">Added validation and two tests</span></div>
          <div><span class="c">↳ status: </span><span class="k">submitted</span></div>
          <div class="gap"></div>
          <div><span class="c"># Reviewer decision</span></div>
          <div><span class="w">↻</span> <span class="v">Changes requested</span></div>
          <div><span class="c">↳ “Please cover the null case too.”</span></div>
          <div><span class="ok">✓</span> <span class="v">Approved</span></div>
        </div>
        <p class="lp-code-note">Example for illustration only, not a real submission.</p>
      </div>
      <div>
        <p class="lp-eyebrow">For contributors</p>
        <h2 id="contributors-title">Do useful work. Keep the proof.</h2>
        <p>Find a task that fits your skills, send your work, and follow every review decision in one place.</p>
        <ul class="lp-checks">
          <li>Browse every bounty without an account</li>
          <li>Submit a pull request or any public evidence link</li>
          <li>Track each decision and reviewer note under Submissions</li>
          <li>Build a developer profile around accepted work</li>
          <li>Learn alongside mentors with SkillMentor <small>(coming soon)</small></li>
        </ul>
        <div class="lp-hero-actions"><a class="lp-btn lp-btn-primary" href="#feed">Find a bounty</a><a class="lp-btn lp-btn-ghost" href="/OpenBounty/skillmentor/">Explore SkillMentor</a></div>
      </div>
    </div>
  </section>

  <!-- Demo activity: illustrative only -->
  <section class="lp-section" aria-labelledby="demo-activity-title">
    <div class="lp-section-head">
      <p class="lp-eyebrow">Demo activity</p>
      <h2 id="demo-activity-title">What a live board looks like</h2>
      <p>Illustrative events shown for the hackathon demo. None of these actions really happened, and demo activity never appears in real notifications.</p>
    </div>
    <div class="ob-card"><ul class="ob-feed">
      <?php foreach (demo_activity() as $a): ?>
      <li><span class="dot"></span><div style="flex:1"><span class="ob-badge amber" style="margin-right:6px">Demo</span><?= e($a['text']) ?><br><small><?= e(ob_ago($a['at'])) ?></small></div></li>
      <?php endforeach; ?>
    </ul></div>
  </section>

  <!-- Closing call to action -->
  <section class="lp-cta" aria-labelledby="cta-title">    <h2 id="cta-title">Browse freely. Sign in when you’re ready.</h2>
    <p>Creating an account takes a minute and unlocks posting bounties, submitting work, and reviewing.</p>
    <div class="lp-hero-actions">
      <?php if(!$u): ?><a class="lp-btn lp-btn-primary lp-btn-lg" href="/OpenBounty/?auth=register" data-open-auth="register">Create your account</a><?php else: ?><a class="lp-btn lp-btn-primary lp-btn-lg" href="/OpenBounty/dashboard/">Go to dashboard</a><?php endif; ?>
      <a class="lp-btn lp-btn-ghost lp-btn-lg" href="/OpenBounty/bounties/">Open the bounty board</a>
    </div>
  </section>

</div>
<?php require __DIR__.'/includes/footer.php'; ?>
