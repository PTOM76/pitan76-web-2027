<?php
/**
 * 上部のバー。トップ以外のページでは、ナビゲーションをトップの各セクションへのリンクにし、ボタン類を出さない。
 * @var array $site @var string $p @var bool $full トップページなら true
 */
$full ??= false;
$top = $full ? '' : $p . 'index.html';
$nav = [
    'works' => ['代表作', 'star'],
    'index' => ['作品一覧', 'table-list'],
    'inventory' => ['技術スタック', 'toolbox'],
    'log' => ['これまで', 'clock-rotate-left'],
];
?>
<header class="bar">
  <a class="bar-logo" href="<?= h($top) ?>#top"><img src="<?= h($p) ?>assets/img/favicon.png" alt="" width="26" height="26"><?= h($site['title']) ?></a>
  <?php if ($full): ?><p class="bar-coords" aria-hidden="true" title="スクロール位置">Y: <span id="coord-y">0</span></p><?php endif; ?>
  <nav class="bar-nav">
    <?php foreach ($nav as $id => [$label, $ic]): ?><a href="<?= h($top) ?>#<?= $id ?>" data-section="<?= $id ?>"><?= icon($ic) ?><?= h($label) ?></a><?php endforeach; ?>
  </nav>
  <?php if ($full): ?>
  <div class="bar-tools">
    <button class="bar-dex" type="button" id="dex-open" title="作品図鑑" aria-label="作品図鑑"><?= icon('book-open') ?></button>
    <button class="bar-theme" type="button" id="theme" title="昼と夜の切り替え" aria-label="昼と夜の切り替え"></button>
    <button class="bar-sound" type="button" id="sound" aria-pressed="true" title="効果音（ON）" aria-label="効果音"><?= icon('volume-high') ?></button>
    <button class="bar-term" type="button" data-open-terminal title="ターミナル（` キー）" aria-label="ターミナル"><?= icon('terminal') ?></button>
  </div>
  <?php endif; ?>
</header>
