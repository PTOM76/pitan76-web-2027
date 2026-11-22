<?php
/** @var array $site @var array $projects @var array $works @var array $categoryLabels */
$cats = [];
foreach ($works as $w) {
    $label = $categoryLabels[$w['category'] ?? 'misc'] ?? 'Misc';
    $cats[$label] = ($cats[$label] ?? 0) + 1;
}
arsort($cats);
$skillIcons = ['LANGUAGES' => '{ }', 'WEB' => '</>', 'INFRASTRUCTURE' => '☁', 'OTHER' => '◆'];
?><!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($site['title']) ?></title>
<meta name="description" content="<?= h($site['description']) ?>">
<meta property="og:title" content="<?= h($site['title']) ?>">
<meta property="og:description" content="<?= h($site['description']) ?>">
<meta property="og:image" content="assets/img/pitan.png">
<meta name="theme-color" content="#0a0d18">
<link rel="icon" href="assets/img/favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Dela+Gothic+One&family=JetBrains+Mono:wght@400;700&family=Zen+Kaku+Gothic+New:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
<script type="importmap">{"imports":{"three":"https://cdn.jsdelivr.net/npm/three@0.170.0/build/three.module.js"}}</script>
</head>
<body>

<div id="boot" aria-hidden="true">
  <pre id="boot-log"></pre>
  <p class="boot-skip">click / any key to skip</p>
</div>

<canvas id="scene" aria-hidden="true"></canvas>
<div class="grain" aria-hidden="true"></div>

<header class="nav">
  <a class="nav-logo" href="#top"><img src="assets/img/favicon.png" alt="" width="28" height="28"><span>PITAN<em>/76</em></span></a>
  <nav>
    <a href="#works">Works</a>
    <a href="#archive">Archive</a>
    <a href="#skills">Skills</a>
    <a href="#log">Log</a>
  </nav>
  <button class="nav-term" type="button" data-open-terminal title="ターミナルを開く（` キー）">&gt;_</button>
</header>

<main id="top">
  <section class="hero">
    <div class="hero-text">
      <p class="eyebrow"><span class="dot"></span> BUG-CREATOR / DEVELOPER</p>
      <h1 class="hero-title" data-scramble="PITAN">PITAN</h1>
      <p class="hero-lead"><?= h($site['lead']) ?></p>
      <div class="hero-actions">
        <a class="btn btn-primary" href="#works">作品を見る</a>
        <a class="btn" href="<?= h($site['links']['github']) ?>" target="_blank" rel="noopener">GitHub ↗</a>
      </div>
      <p class="hero-hint">💡 背景のブロックはクリックで壊せます</p>
    </div>
    <div class="hero-avatar">
      <div class="avatar-ring"></div>
      <button class="avatar" type="button" id="pitan" aria-label="ぴたんをなでる">
        <img src="assets/img/pitan-cut.png" alt="ぴたん" width="640" height="640">
      </button>
      <div class="bubble" id="pitan-bubble" role="status"></div>
    </div>
    <a class="scroll-cue" href="#stats" aria-label="下へスクロール"><span></span></a>
  </section>

  <div class="marquee" aria-hidden="true">
    <div class="marquee-track">
      <?php for ($i = 0; $i < 2; $i++): foreach ($site['skills'] as $g): foreach ($g['items'] as $it): ?>
        <span><?= h($it) ?></span><i>✦</i>
      <?php endforeach; endforeach; endfor; ?>
    </div>
  </div>

  <section class="stats" id="stats">
    <?php foreach ($site['achievements'] as $a): ?>
      <div class="stat reveal">
        <strong data-count="<?= h($a['value']) ?>"><?= h($a['value']) ?></strong>
        <span><?= h($a['label']) ?></span>
      </div>
    <?php endforeach; ?>
    <div class="stat reveal">
      <strong data-count="<?= count($works) ?>"><?= count($works) ?></strong>
      <span>公開作品（works-info登録数）</span>
    </div>
  </section>

  <section class="section" id="works">
    <h2 class="section-title reveal"><span>01</span>Selected Works</h2>
    <div class="works">
      <?php foreach ($projects as $i => $p): ?>
        <article class="work reveal">
          <a class="work-visual tilt" href="<?= h($p['url']) ?>" target="_blank" rel="noopener" tabindex="-1">
            <?php if (!empty($p['image'])): ?>
              <img src="<?= h($p['image']) ?>" alt="" loading="lazy">
            <?php else: ?>
              <div class="work-ph"><?= h($p['name']) ?></div>
            <?php endif; ?>
            <span class="work-no"><?= sprintf('%02d', $i + 1) ?></span>
          </a>
          <div class="work-body">
            <p class="work-cat"><?= h($p['category']) ?></p>
            <h3><a href="<?= h($p['url']) ?>" target="_blank" rel="noopener"><?= h($p['name']) ?></a></h3>
            <p class="work-summary"><?= h($p['summary']) ?></p>
            <p class="work-detail"><?= h($p['detail']) ?></p>
            <ul class="tags"><?php foreach ($p['tags'] as $t): ?><li><?= h($t) ?></li><?php endforeach; ?></ul>
            <p class="work-links">
              <a href="<?= h($p['url']) ?>" target="_blank" rel="noopener">Visit ↗</a>
              <?php if (!empty($p['source'])): ?><a href="<?= h($p['source']) ?>" target="_blank" rel="noopener">Source ↗</a><?php endif; ?>
            </p>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

  <?php if ($works): ?>
  <section class="section" id="archive">
    <h2 class="section-title reveal"><span>02</span>Archive</h2>
    <p class="section-note reveal">これまでに公開した <?= count($works) ?> 作品。データは <a href="https://github.com/PTOM76/works-info" target="_blank" rel="noopener">works-info</a> から取得しています。</p>
    <div class="archive-tools reveal">
      <input type="search" id="archive-q" placeholder="作品を検索…" aria-label="作品を検索">
      <div class="chips" role="group" aria-label="カテゴリ">
        <button type="button" class="chip is-on" data-cat="">All <small><?= count($works) ?></small></button>
        <?php foreach ($cats as $c => $n): ?>
          <button type="button" class="chip" data-cat="<?= h($c) ?>"><?= h($c) ?> <small><?= $n ?></small></button>
        <?php endforeach; ?>
      </div>
    </div>
    <ul class="archive" id="archive-list">
      <?php foreach ($works as $w):
        $label = $categoryLabels[$w['category'] ?? 'misc'] ?? 'Misc';
        $link = $w['site']['modparks'] ?? $w['url'] ?? $w['site']['github'] ?? '#';
        $img = $w['custom']['images'][0] ?? null;
        $year = substr($w['updateAt'] ?? $w['createAt'] ?? '', 0, 4);
      ?>
        <li class="aw" data-cat="<?= h($label) ?>" data-q="<?= h(mb_strtolower($w['name'] . ' ' . ($w['description'] ?? '') . ' ' . implode(' ', $w['tag'] ?? []))) ?>">
          <a href="<?= h($link) ?>" target="_blank" rel="noopener">
            <div class="aw-img">
              <?php if ($img): ?><img src="<?= h($img) ?>" alt="" loading="lazy"><?php else: ?><span><?= h(mb_substr($w['name'], 0, 1)) ?></span><?php endif; ?>
            </div>
            <div class="aw-body">
              <p class="aw-meta"><?= h($label) ?><?php if ($year): ?> · <?= h($year) ?><?php endif; ?></p>
              <h3><?= h($w['name']) ?></h3>
              <p><?= h($w['description'] ?? '') ?></p>
            </div>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
    <p class="archive-empty" id="archive-empty" hidden>該当する作品はありません。</p>
  </section>
  <?php endif; ?>

  <section class="section" id="skills">
    <h2 class="section-title reveal"><span>03</span>Inventory</h2>
    <div class="inventory">
      <?php foreach ($site['skills'] as $g): ?>
        <div class="inv-group reveal">
          <p class="inv-label"><b><?= h($skillIcons[$g['name']] ?? '◆') ?></b><?= h($g['name']) ?></p>
          <ul class="inv-grid">
            <?php foreach ($g['items'] as $it): ?><li class="slot" tabindex="0"><?= h($it) ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="section" id="log">
    <h2 class="section-title reveal"><span>04</span>Progress Log</h2>
    <div class="log-wrap">
      <ol class="timeline">
        <?php foreach ($site['history'] as $i => $hst): ?>
          <li class="reveal"><span class="lv">LV.<?= $i + 1 ?></span><b><?= h($hst['period']) ?></b><p><?= h($hst['items']) ?></p></li>
        <?php endforeach; ?>
      </ol>
      <div class="unlocks">
        <p class="unlocks-title">Achievements Unlocked</p>
        <?php foreach ($site['qualifications'] as $q): ?>
          <div class="unlock reveal"><span class="trophy">🏆</span><div><small>資格取得</small><b><?= h($q['name']) ?></b></div></div>
        <?php endforeach; ?>
        <?php foreach ($site['activities'] as $act): ?>
          <div class="unlock reveal"><span class="trophy">⭐</span><div><small>活動</small><b><?= h($act) ?></b></div></div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="section friends" id="friends">
    <h2 class="section-title reveal"><span>05</span>Friends</h2>
    <div class="friend-row">
      <button type="button" class="friend reveal" data-friend="ftp">
        <img src="assets/img/ftp.png" alt="FTPちゃん" loading="lazy">
        <span class="friend-name">FTP</span>
      </button>
      <button type="button" class="friend reveal" data-friend="http">
        <img src="assets/img/http.png" alt="HTTPちゃん" loading="lazy">
        <span class="friend-name">HTTP</span>
      </button>
    </div>
  </section>
</main>

<footer class="footer">
  <p class="footer-big">Let's build<br>something buggy.</p>
  <p><a href="<?= h($site['links']['github']) ?>" target="_blank" rel="noopener">GitHub ↗</a></p>
  <p class="footer-small">© <?= date('Y') ?> <?= h($site['author']) ?> · <kbd>`</kbd> でターミナル · 隠しコマンドあり</p>
</footer>

<img class="peek" id="peek" src="assets/img/ftp.png" alt="" aria-hidden="true">

<div class="terminal" id="terminal" hidden>
  <div class="term-win" role="dialog" aria-label="ターミナル">
    <div class="term-bar"><span></span><span></span><span></span><p>pitan@bug-creator: ~</p><button type="button" data-close-terminal aria-label="閉じる">×</button></div>
    <div class="term-out" id="term-out"></div>
    <form class="term-in" id="term-form"><label for="term-input">$</label><input id="term-input" autocomplete="off" spellcheck="false"></form>
  </div>
</div>

<div class="toasts" id="toasts" aria-live="polite"></div>
<div class="debug" id="debug" hidden></div>

<script>window.SITE = <?= json_encode([
    'github' => $site['links']['github'],
    'projects' => array_map(static fn($p) => ['name' => $p['name'], 'url' => $p['url'], 'summary' => $p['summary']], $projects),
    'skills' => $site['skills'],
    'works' => count($works),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>;</script>
<script type="module" src="assets/scene.js"></script>
<script src="assets/main.js" defer></script>
</body>
</html>
