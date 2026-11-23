<?php
/**
 * <head> の共通部分。
 * @var array $site @var string $p @var string $url
 * @var string $title ページのタイトル @var string $description @var string $path 公開先からのパス（OGP 用）
 * @var string|null $image OGP 画像（アセットのパス）
 * @var bool $iconFont Font Awesome を読み込むなら true（アイコンを使わないページでは false）
 */
$image ??= 'assets/img/og.png';
$iconFont ??= true;
$large = $image === 'assets/img/og.png' || str_ends_with($image, '-w.jpg');
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?></title>
<meta name="description" content="<?= h($description) ?>">
<meta property="og:site_name" content="<?= h($site['title']) ?>">
<meta property="og:title" content="<?= h($title) ?>">
<meta property="og:description" content="<?= h($description) ?>">
<meta property="og:type" content="<?= $path === '' ? 'website' : 'article' ?>">
<?php if ($url !== ''): ?><meta property="og:url" content="<?= h("$url/$path") ?>"><?php endif; ?>
<meta property="og:image" content="<?= h(($url !== '' ? "$url/" : $p) . $image) ?>">
<meta name="twitter:card" content="<?= $large ? 'summary_large_image' : 'summary' ?>">
<?php if ($url !== ''): ?><link rel="canonical" href="<?= h("$url/$path") ?>"><?php endif; ?>
<meta name="theme-color" content="#dcecfa">
<link rel="icon" href="<?= h($p) ?>assets/img/favicon-32.png" sizes="32x32">
<link rel="icon" href="<?= h($p) ?>assets/img/favicon.png" sizes="64x64">
<link rel="apple-touch-icon" href="<?= h($p) ?>assets/img/apple-touch-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DotGothic16&family=JetBrains+Mono:wght@400;700&family=Zen+Kaku+Gothic+New:wght@400;700;900&display=swap" rel="stylesheet">
<?php if ($iconFont): ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/fontawesome.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/solid.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/brands.min.css">
<?php endif; ?>
<link rel="stylesheet" href="<?= h(asset('assets/style.css')) ?>">
<script>
// 表示前にテーマを決め、ちらつきを防ぐ。auto は 6〜18 時を昼、それ以外を夜にする
(() => {
  let m = 'auto';
  try { m = localStorage.getItem('theme') || 'auto'; } catch { /* noop */ }
  const h = new Date().getHours();
  document.documentElement.dataset.theme = m === 'auto' ? (h >= 6 && h < 18 ? 'light' : 'dark') : m;
  document.documentElement.dataset.themeMode = m;
})();
</script>
