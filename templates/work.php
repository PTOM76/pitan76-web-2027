<?php
/**
 * 作品ごとの詳細ページ（works/ID.html）。JavaScript なしで読める。
 * @var array $site @var string $p @var array $w build_items() の要素
 */
?><!doctype html>
<html lang="ja">
<head>
<?= partial('head', [
    'title' => "{$w['name']} — {$site['title']}",
    'description' => $w['desc'] ?: "{$w['name']}（{$w['cat']}）",
    'path' => $w['page'],
    'image' => $w['thumb'] ? "{$w['thumb']}-w.jpg" : null,
]) ?>
</head>
<body class="page-work">

<svg class="defs" aria-hidden="true" width="0" height="0">
  <filter id="rough"><feTurbulence type="fractalNoise" baseFrequency="0.035" numOctaves="2" seed="76"/><feDisplacementMap in="SourceGraphic" scale="3.2"/></filter>
</svg>

<?= partial('bar') ?>

<main class="work-page">
  <p class="crumb"><a href="<?= h($p) ?>index.html#index">作品一覧</a> / <?= h($w['cat']) ?></p>
  <article class="loot-card work-detail-card sk">
<?= partial('work-card', ['w' => $w]) ?>
  </article>
</main>

<?= partial('footer') ?>
</body>
</html>
