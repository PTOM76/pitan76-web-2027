<?php
/**
 * 作品ごとの詳細ページ（works/ID.html）。JavaScript なしで読める。
 * @var array $site @var string $p @var array $w build_items() の要素
 */
$spec = work_spec($w);
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
    <?php if ($w['thumb']): ?><img src="<?= h($p . $w['thumb']) ?>-w.jpg" alt="<?= h($w['name']) ?>の画面" width="640" height="360"><?php endif; ?>
    <h1><?= h($w['name']) ?></h1>
    <small><?= h(implode(' · ', array_filter([$w['cat'], $w['year']]))) ?></small>
    <?php if ($w['desc']): ?><p><?= h($w['desc']) ?></p><?php endif; ?>
    <?php if ($w['note']): ?><div class="loot-note"><span>つくった理由</span><p><?= h($w['note']) ?></p></div><?php endif; ?>
    <?php if ($spec): ?>
    <dl class="loot-spec">
      <?php foreach ($spec as $k => $v): ?><dt><?= h($k) ?></dt><dd><?= h($v) ?></dd><?php endforeach; ?>
    </dl>
    <?php endif; ?>
    <?php if ($w['tags']): ?><p class="loot-tags"><?= h(implode(' ', array_map(static fn($t) => "#$t", $w['tags']))) ?></p><?php endif; ?>
    <p class="loot-links">
      <?php foreach ($w['links'] ?: [['label' => '開く', 'url' => $w['url'] ?: $site['links']['github']]] as $l): ?>
        <a href="<?= h($l['url']) ?>" target="_blank" rel="noopener"><?= h($l['label']) ?></a>
      <?php endforeach; ?>
    </p>
  </article>
</main>

<?= partial('footer') ?>
</body>
</html>
