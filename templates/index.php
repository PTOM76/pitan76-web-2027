<?php
/**
 * トップページ。各セクションは templates/partials/ にある。
 * @var array $site @var array $config @var array $projects @var array $items @var array $icons @var array $characters
 * @var array $cats @var array $log @var string $facts
 */
?><!doctype html>
<html lang="ja">
<head>
<?= partial('head', ['title' => $site['title'], 'description' => $site['description'], 'path' => '']) ?>
<script type="importmap">{"imports":{"three":"https://cdn.jsdelivr.net/npm/three@0.170.0/build/three.module.js"}}</script>
<link rel="modulepreload" href="https://cdn.jsdelivr.net/npm/three@0.170.0/build/three.module.js" crossorigin>
</head>
<body>

<!-- 手描き風の揺れた線（キャラクターの線画に合わせる） -->
<svg class="defs" aria-hidden="true" width="0" height="0">
  <filter id="rough"><feTurbulence type="fractalNoise" baseFrequency="0.035" numOctaves="2" seed="76"/><feDisplacementMap in="SourceGraphic" scale="3.2"/></filter>
</svg>

<canvas id="scene" aria-hidden="true"></canvas>

<?= partial('bar', ['full' => true]) ?>

<main id="top">
<?= partial('hero', ['log' => $log, 'facts' => $facts]) ?>
<?= partial('featured', ['projects' => $projects]) ?>
<?= partial('works-index', ['items' => $items, 'cats' => $cats]) ?>
<?= partial('inventory', ['icons' => $icons]) ?>
<?= partial('history') ?>
</main>

<?= partial('footer', ['full' => true]) ?>
<?= partial('overlays', ['items' => $items]) ?>

<script>window.SITE = <?= js_json([
    'github' => $site['links']['github'],
    'projects' => array_map(static fn($p) => ['name' => $p['name'], 'url' => $p['url']], $projects),
    'characters' => $characters,
    'term' => terminal_text($site, $projects),
    // main.js が動的に出すアイコン（Font Awesome の solid）。名前 => [幅, 高さ, パス]
    'icons' => fa_icons()['solid'],
    'works' => array_map(static fn($w) => array_intersect_key($w, array_flip(['id', 'page', 'card', 'name', 'desc', 'cat', 'tags', 'thumb', 'faces', 'icon', 'featured', 'url'])), $items),
]) ?>;</script>
<script type="module" src="<?= h(asset('assets/scene.js')) ?>"></script>
<script src="<?= h(asset('assets/main.js')) ?>" defer></script>
</body>
</html>
