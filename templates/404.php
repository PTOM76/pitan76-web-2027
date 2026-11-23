<?php
/**
 * 404 ページ。どの階層の URL でも表示されるため、リンクと画像は公開先のパス（$p = PITAN_BASE）から書く。
 * @var array $site @var string $p
 */
?><!doctype html>
<html lang="ja">
<head>
<?= partial('head', ['title' => "404 Not Found — {$site['title']}", 'description' => 'ページが見つかりませんでした。', 'path' => '404.html', 'iconFont' => false]) ?>
<meta name="robots" content="noindex">
</head>
<body class="page-404">
  <main class="nf">
    <img src="<?= h($p) ?>assets/img/http.png" alt="HTTPプロトコルさん" width="220">
    <div class="nf-res">
      <h1>404</h1>
<pre>HTTP/1.1 404 Not Found
X-Served-By: HTTPプロトコルさん

そのページは見つかりませんでした。</pre>
      <a href="<?= h($p) ?>">トップへ戻る</a>
    </div>
  </main>
</body>
</html>
