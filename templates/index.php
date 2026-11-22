<?php
/** @var array $site @var array $projects @var array $items @var array $icons */
$cats = array_count_values(array_column($items, 'cat'));
arsort($cats);
$log = [];
foreach ($items as $w) {
    if ($w['date'] === '') continue;
    $key = $w['date'] . ($w['updated'] ? 'u' : 'n');
    if (!isset($log[$key])) {
        if (count($log) >= 4) break;
        $log[$key] = ['date' => $w['date'], 'updated' => $w['updated'], 'works' => []];
    }
    $log[$key]['works'][] = $w;
}
$ach = array_column($site['achievements'], 'value', 'label');
$num = static fn(string $label): string => h(rtrim($ach[$label] ?? '', '+'));
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
<meta name="theme-color" content="#dcecfa">
<link rel="icon" href="assets/img/favicon-32.png" sizes="32x32">
<link rel="icon" href="assets/img/favicon.png" sizes="64x64">
<link rel="apple-touch-icon" href="assets/img/apple-touch-icon.png">
<meta property="og:site_name" content="<?= h($site['title']) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DotGothic16&family=JetBrains+Mono:wght@400;700&family=Zen+Kaku+Gothic+New:wght@400;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
<script type="importmap">{"imports":{"three":"https://cdn.jsdelivr.net/npm/three@0.170.0/build/three.module.js"}}</script>
</head>
<body>

<!-- 手描き風の揺れた線（キャラクターの線画に合わせる） -->
<svg class="defs" aria-hidden="true" width="0" height="0">
  <filter id="rough"><feTurbulence type="fractalNoise" baseFrequency="0.035" numOctaves="2" seed="76"/><feDisplacementMap in="SourceGraphic" scale="3.2"/></filter>
</svg>

<canvas id="scene" aria-hidden="true"></canvas>

<header class="bar">
  <a class="bar-logo" href="#top"><img src="assets/img/favicon.png" alt="" width="26" height="26"><?= h($site['title']) ?></a>
  <p class="bar-coords" aria-hidden="true" title="スクロール位置">Y: <span id="coord-y">0</span></p>
  <nav>
    <a href="#works">代表作</a>
    <a href="#index">作品一覧</a>
    <a href="#inventory">技術スタック</a>
    <a href="#log">これまで</a>
  </nav>
  <button class="bar-sound" type="button" id="sound" aria-pressed="true" title="効果音">♪ ON</button>
  <button class="bar-term" type="button" data-open-terminal title="ターミナル（` キー）">&gt;_</button>
</header>

<main id="top">
  <section class="hero">
    <div class="hero-text">
      <h1 class="hero-title"><span class="ht-jp">ぴたん</span><span class="ht-en">PITAN</span></h1>
      <p class="hero-lead"><?= h($site['lead']) ?></p>
      <p class="hero-facts">
        Minecraft Modは<b class="num"><?= $num('Minecraft Mod 開発作品') ?></b>作以上つくり、ダウンロード数は合計<b class="num"><?= $num('Minecraft Mod 累計ダウンロード') ?></b>回を超えました。WikiChree.COMでは<b class="num"><?= $num('WikiChree.COM 運営Wiki') ?></b>のWikiが動いています。
      </p>
      <?php if ($log): ?>
      <ol class="chatlog" aria-label="最近の更新">
        <?php foreach ($log as $e): $first = $e['works'][0]; $more = count($e['works']) - 1; ?>
          <li><time datetime="<?= h($e['date']) ?>">[<?= h(date('n/j', strtotime($e['date']))) ?>]</time> <a href="<?= h($first['url'] ?: '#') ?>" target="_blank" rel="noopener"><?= h($first['name']) ?></a><?php if ($more): ?> ほか<?= $more ?>件<?php endif; ?>を<?= $e['updated'] ? '更新' : '公開' ?>しました</li>
        <?php endforeach; ?>
      </ol>
      <?php endif; ?>
      <p class="hero-hint">背景のブロックは長押しで掘れます。色の粒が入ったブロックや絵の付いたブロックには、作品が埋まっています。</p>
    </div>
    <div class="hero-sticker">
      <button class="sticker" type="button" id="pitan" aria-label="ぴたんをなでる">
        <img src="assets/img/pitan.png" alt="ぴたん" width="640" height="640" draggable="false">
      </button>
      <p class="say sk" id="pitan-say" role="status"></p>
    </div>
  </section>


  <section class="section" id="works">
    <h2 class="sec-title">代表作</h2>
    <?php foreach ($projects as $i => $p): ?>
      <article class="work">
        <div class="work-text sk">
          <p class="work-cat"><?= h($p['category']) ?></p>
          <h3><a href="<?= h($p['url']) ?>" target="_blank" rel="noopener"><?= h($p['name']) ?></a></h3>
          <p class="work-summary"><?= h($p['summary']) ?></p>
          <p class="work-detail"><?= h($p['detail']) ?></p>
          <ul class="tags"><?php foreach ($p['tags'] as $t): ?><li><?= h($t) ?></li><?php endforeach; ?></ul>
          <p class="work-links">
            <a class="sk" href="<?= h($p['url']) ?>" target="_blank" rel="noopener">サイトを開く</a>
            <?php if (!empty($p['source'])): ?><a class="sk" href="<?= h($p['source']) ?>" target="_blank" rel="noopener">ソースコード</a><?php endif; ?>
          </p>
        </div>
        <figure class="work-slot" data-slot="<?= $i ?>">
          <?php if ($p['thumb']): ?><img src="<?= h($p['thumb']) ?>-w.jpg" alt="<?= h($p['name']) ?>の画面" loading="lazy" width="640" height="360"><?php endif; ?>
        </figure>
      </article>
    <?php endforeach; ?>
  </section>

  <?php if ($items): ?>
  <section class="section" id="index">
    <h2 class="sec-title">作品一覧 <small><?= count($items) ?>件</small></h2>
    <div class="index-tools">
      <input type="search" id="index-q" placeholder="名前や説明で検索" aria-label="作品を検索">
      <div class="filters" role="group" aria-label="カテゴリ">
        <button type="button" class="filter sk is-on" data-cat="">すべて<small><?= count($items) ?></small></button>
        <?php foreach ($cats as $c => $n): ?>
          <button type="button" class="filter sk" data-cat="<?= h($c) ?>"><?= h($c) ?><small><?= $n ?></small></button>
        <?php endforeach; ?>
      </div>
    </div>
    <ol class="index sk" id="index-list">
      <?php foreach ($items as $n => $w): ?>
        <li<?php if ($n >= 24): ?> class="is-more"<?php endif; ?> data-id="<?= h($w['id']) ?>" data-cat="<?= h($w['cat']) ?>" data-q="<?= h(mb_strtolower($w['name'] . ' ' . $w['desc'] . ' ' . implode(' ', $w['tags']))) ?>"<?php if ($w['thumb']): ?> data-thumb="<?= h($w['thumb']) ?>-w.jpg"<?php endif; ?>>
          <a href="<?= h($w['url'] ?: '#') ?>" target="_blank" rel="noopener">
            <span class="ix-name"><?= h($w['name']) ?></span>
            <span class="ix-desc"><?= h($w['desc']) ?></span>
            <span class="ix-cat"><?= h($w['cat']) ?></span>
            <span class="ix-year"><?= h($w['year']) ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ol>
    <?php if (count($items) > 24): ?><button type="button" class="index-more sk" id="index-more">残りの<?= count($items) - 24 ?>件も表示</button><?php endif; ?>
    <div class="index-empty" id="index-empty" hidden>
      <img src="assets/img/http.png" alt="" width="160">
      <p class="sk"><b>HTTP/1.1 404 Not Found</b><br>そんな作品はないみたい。別の言葉で検索してください。</p>
    </div>
    <img class="index-preview" id="index-preview" alt="" aria-hidden="true">
  </section>
  <?php endif; ?>

  <section class="section" id="inventory">
    <h2 class="sec-title">技術スタック</h2>
    <div class="inventory">
      <?php foreach ($site['skills'] as $g): ?>
        <div class="inv sk">
          <h3 class="inv-label"><?= h(['LANGUAGES' => '言語', 'WEB' => 'Web', 'INFRASTRUCTURE' => 'インフラ', 'OTHER' => 'そのほか'][$g['name']] ?? $g['name']) ?></h3>
          <ul>
            <?php foreach ($g['items'] as $it): $ic = $icons[$it] ?? null; ?>
              <li class="slot" tabindex="0">
                <?php if ($ic): ?>
                  <span class="slot-icons<?= count($ic) > 1 ? ' is-pair' : '' ?>" aria-hidden="true"><?php foreach ($ic as $file): ?><img src="assets/icons/<?= h($file) ?>" alt="" width="34" height="34" loading="lazy" draggable="false"><?php endforeach; ?></span>
                <?php else: ?>
                  <span class="slot-mark" aria-hidden="true"><?= h(mb_substr(preg_replace('/[^A-Za-z0-9#]/', '', $it), 0, 2)) ?></span>
                <?php endif; ?>
                <span class="slot-name"><?= h($it) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="section" id="log">
    <h2 class="sec-title">これまで</h2>
    <div class="log">
      <ol class="history">
        <?php foreach ($site['history'] as $hst): ?>
          <li><b><?= h($hst['period']) ?></b><span><?= h($hst['items']) ?></span></li>
        <?php endforeach; ?>
      </ol>
      <div class="unlocks sk">
        <h3>資格</h3>
        <ul>
          <?php foreach ($site['qualifications'] as $q): ?><li><?= h($q['name']) ?></li><?php endforeach; ?>
        </ul>
        <h3>活動</h3>
        <ul>
          <?php foreach ($site['activities'] as $act): ?><li><?= h($act) ?></li><?php endforeach; ?>
        </ul>
      </div>
    </div>
  </section>
</main>

<footer class="footer">
  <a href="<?= h($site['links']['github']) ?>" target="_blank" rel="noopener">GitHub</a>
  <p>© <?= date('Y') ?> <?= h($site['author']) ?></p>
  <p class="footer-hint"><kbd>`</kbd> キーでターミナルが開きます</p>
</footer>

<div class="courier" id="courier" aria-hidden="true">
  <img src="assets/img/ftp.png" alt="">
  <div class="courier-log"><span id="courier-cmd"></span><i><b id="courier-bar"></b></i></div>
</div>

<div class="loot" id="loot" hidden>
  <a class="loot-card sk" id="loot-card" target="_blank" rel="noopener">
    <img id="loot-img" alt="">
    <span class="loot-get">掘り当てた！</span>
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
