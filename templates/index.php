<?php
/** @var array $site @var array $projects @var array $items */
$cats = array_count_values(array_column($items, 'cat'));
arsort($cats);
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
<meta name="theme-color" content="#ece8dd">
<link rel="icon" href="assets/img/favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DotGothic16&family=JetBrains+Mono:wght@400;700&family=Zen+Kaku+Gothic+New:wght@400;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
<script type="importmap">{"imports":{"three":"https://cdn.jsdelivr.net/npm/three@0.170.0/build/three.module.js"}}</script>
</head>
<body>

<div id="boot" aria-hidden="true"><pre id="boot-log"></pre><p>click / any key to skip</p></div>

<canvas id="scene" aria-hidden="true"></canvas>

<header class="bar">
  <a class="bar-logo" href="#top"><img src="assets/img/favicon.png" alt="" width="24" height="24">PITAN.76</a>
  <p class="bar-coords" aria-hidden="true">XYZ <span id="coord-y">0</span> / <span id="coord-p">0</span>%</p>
  <nav>
    <a href="#works">Works</a>
    <a href="#index">Index</a>
    <a href="#inventory">Skills</a>
    <a href="#log">Log</a>
  </nav>
  <button class="bar-sound" type="button" id="sound" aria-pressed="true" title="効果音">♪ ON</button>
  <button class="bar-term" type="button" data-open-terminal title="ターミナル（` キー）">&gt;_</button>
</header>

<main id="top">
  <section class="hero">
    <div class="hero-meta">
      <dl class="spec">
        <div><dt>NAME</dt><dd>Pitan / ぴたん</dd></div>
        <div><dt>CLASS</dt><dd>Bug-Creator</dd></div>
        <div><dt>MAIN</dt><dd>Minecraft Mod / Web</dd></div>
        <div><dt>WORKS</dt><dd><?= count($items) ?> entries</dd></div>
      </dl>
    </div>
    <h1 class="hero-title"><span class="ht-jp">ぴたん</span><span class="ht-en" data-scramble="PITAN">PITAN</span></h1>
    <p class="hero-lead"><?= h($site['lead']) ?></p>
    <div class="hero-sticker">
      <button class="sticker" type="button" id="pitan" aria-label="ぴたんをなでる">
        <img src="assets/img/pitan.png" alt="ぴたん" width="640" height="640">
      </button>
      <p class="say" id="pitan-say" role="status"></p>
    </div>
    <p class="hero-hint"><span>TIP</span>背景のブロックは長押しで掘れます。中から作品が出てきます。</p>
  </section>

  <div class="ticker" aria-hidden="true"><div class="ticker-track">
    <?php for ($i = 0; $i < 2; $i++): foreach ($site['achievements'] as $a): ?>
      <span><b><?= h($a['value']) ?></b> <?= h($a['label']) ?></span><i>■</i>
    <?php endforeach; endfor; ?>
  </div></div>

  <section class="section" id="works">
    <header class="sec-head"><p class="sec-no">§1</p><h2>代表作</h2><p class="sec-en">Selected works</p></header>
    <?php foreach ($projects as $i => $p): ?>
      <article class="work" data-feature="<?= $i ?>">
        <div class="work-text">
          <p class="work-no"><?= sprintf('%02d', $i + 1) ?></p>
          <p class="work-cat"><?= h($p['category']) ?></p>
          <h3><a href="<?= h($p['url']) ?>" target="_blank" rel="noopener"><?= h($p['name']) ?></a></h3>
          <p class="work-summary"><?= h($p['summary']) ?></p>
          <p class="work-detail"><?= h($p['detail']) ?></p>
          <ul class="tags"><?php foreach ($p['tags'] as $t): ?><li><?= h($t) ?></li><?php endforeach; ?></ul>
          <p class="work-links">
            <a href="<?= h($p['url']) ?>" target="_blank" rel="noopener">サイトを開く →</a>
            <?php if (!empty($p['source'])): ?><a href="<?= h($p['source']) ?>" target="_blank" rel="noopener">ソース →</a><?php endif; ?>
          </p>
        </div>
        <figure class="work-slot" data-slot="<?= $i ?>">
          <?php if ($p['thumb']): ?><img src="<?= h($p['thumb']) ?>-w.jpg" alt="" loading="lazy" width="640" height="360"><?php endif; ?>
          <figcaption>fig.<?= sprintf('%02d', $i + 1) ?> — <?= h($p['name']) ?></figcaption>
        </figure>
      </article>
    <?php endforeach; ?>
  </section>

  <?php if ($items): ?>
  <section class="section" id="index">
    <header class="sec-head"><p class="sec-no">§2</p><h2>作品索引</h2><p class="sec-en">Index of <?= count($items) ?> works</p></header>
    <div class="index-tools">
      <input type="search" id="index-q" placeholder="検索: mod, wiki, rust …" aria-label="作品を検索">
      <div class="filters" role="group" aria-label="カテゴリ">
        <button type="button" class="filter is-on" data-cat="">すべて<small><?= count($items) ?></small></button>
        <?php foreach ($cats as $c => $n): ?>
          <button type="button" class="filter" data-cat="<?= h($c) ?>"><?= h($c) ?><small><?= $n ?></small></button>
        <?php endforeach; ?>
      </div>
    </div>
    <ol class="index" id="index-list">
      <?php foreach ($items as $n => $w): ?>
        <li<?php if ($n >= 24): ?> class="is-more"<?php endif; ?> data-id="<?= h($w['id']) ?>" data-cat="<?= h($w['cat']) ?>" data-q="<?= h(mb_strtolower($w['name'] . ' ' . $w['desc'] . ' ' . implode(' ', $w['tags']))) ?>"<?php if ($w['thumb']): ?> data-thumb="<?= h($w['thumb']) ?>-w.jpg"<?php endif; ?>>
          <a href="<?= h($w['url'] ?: '#') ?>" target="_blank" rel="noopener">
            <span class="ix-no"><?= sprintf('%03d', $n + 1) ?></span>
            <span class="ix-name"><?= h($w['name']) ?></span>
            <span class="ix-desc"><?= h($w['desc']) ?></span>
            <span class="ix-cat"><?= h($w['cat']) ?></span>
            <span class="ix-year"><?= h($w['year']) ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ol>
    <?php if (count($items) > 24): ?><button type="button" class="index-more" id="index-more">すべて表示（残り <?= count($items) - 24 ?> 件）</button><?php endif; ?>
    <div class="index-empty" id="index-empty" hidden>
      <img src="assets/img/http.png" alt="" width="160">
      <p><b>HTTP/1.1 404 Not Found</b><br>そんな作品はないみたい。別のキーワードで検索してください。</p>
    </div>
    <img class="index-preview" id="index-preview" alt="" aria-hidden="true">
  </section>
  <?php endif; ?>

  <section class="section" id="inventory">
    <header class="sec-head"><p class="sec-no">§3</p><h2>持ち物</h2><p class="sec-en">Inventory</p></header>
    <div class="inventory">
      <?php foreach ($site['skills'] as $g): ?>
        <div class="inv">
          <p class="inv-label"><?= h($g['name']) ?></p>
          <ul><?php foreach ($g['items'] as $it): ?><li class="slot" tabindex="0"><?= h($it) ?></li><?php endforeach; ?></ul>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="section" id="log">
    <header class="sec-head"><p class="sec-no">§4</p><h2>記録</h2><p class="sec-en">Log</p></header>
    <div class="log">
      <table class="history">
        <tbody>
          <?php foreach ($site['history'] as $i => $hst): ?>
            <tr><th><span>LV.<?= $i + 1 ?></span><?= h($hst['period']) ?></th><td><?= h($hst['items']) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <ul class="unlocks">
        <?php foreach ($site['qualifications'] as $q): ?>
          <li><small>取得</small><?= h($q['name']) ?></li>
        <?php endforeach; ?>
        <?php foreach ($site['activities'] as $act): ?>
          <li><small>活動</small><?= h($act) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>

</main>

<footer class="footer">
  <p class="footer-big">EOF<span class="caret">_</span></p>
  <div class="footer-row">
    <a href="<?= h($site['links']['github']) ?>" target="_blank" rel="noopener">GitHub →</a>
    <p>© <?= date('Y') ?> <?= h($site['author']) ?> · <kbd>`</kbd> ターミナル · 隠しコマンドあり</p>
  </div>
</footer>

<img class="peek" id="peek" src="assets/img/ftp.png" alt="" aria-hidden="true">

<div class="courier" id="courier" aria-hidden="true">
  <img src="assets/img/ftp.png" alt="">
  <div class="courier-log"><span id="courier-cmd"></span><i><b id="courier-bar"></b></i></div>
</div>

<div class="loot" id="loot" hidden>
  <a class="loot-card" id="loot-card" target="_blank" rel="noopener">
    <img id="loot-img" alt="">
    <span class="loot-get">ITEM GET</span>
    <b id="loot-name"></b>
    <small id="loot-meta"></small>
    <span id="loot-desc"></span>
  </a>
</div>

<div class="terminal" id="terminal" hidden>
  <div class="term-win" role="dialog" aria-label="ターミナル">
    <div class="term-bar"><p>pitan@bug-creator:~</p><button type="button" data-close-terminal aria-label="閉じる">×</button></div>
    <div class="term-out" id="term-out"></div>
    <form class="term-in" id="term-form"><label for="term-input">$</label><input id="term-input" autocomplete="off" spellcheck="false"></form>
  </div>
</div>

<div class="block-tip" id="block-tip" aria-hidden="true"></div>
<div class="toasts" id="toasts" aria-live="polite"></div>
<pre class="debug" id="debug" hidden></pre>

<script>window.SITE = <?= json_encode([
    'github' => $site['links']['github'],
    'projects' => array_map(static fn($p) => ['name' => $p['name'], 'url' => $p['url'], 'summary' => $p['summary']], $projects),
    'skills' => $site['skills'],
    'works' => $items,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script type="module" src="assets/scene.js"></script>
<script src="assets/main.js" defer></script>
</body>
</html>
