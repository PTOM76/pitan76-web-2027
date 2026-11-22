<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>404 — <?= h($site['author']) ?></title>
<link rel="icon" href="/assets/img/favicon.png">
<style>
body{margin:0;min-height:100vh;display:grid;place-items:center;background:#0a0d18;color:#e8eeff;font-family:"JetBrains Mono",monospace;text-align:center}
img{width:180px;filter:drop-shadow(0 0 0 #fff) drop-shadow(3px 0 0 #fff) drop-shadow(-3px 0 0 #fff) drop-shadow(0 3px 0 #fff) drop-shadow(0 -3px 0 #fff);animation:s 3s ease-in-out infinite}
h1{font-size:clamp(48px,12vw,120px);margin:.2em 0 0;color:#9fd3ff}
a{color:#ffb627}
@keyframes s{50%{transform:rotate(-8deg) translateY(-6px)}}
</style>
</head>
<body>
<div>
<img src="/assets/img/pitan-cut.png" alt="">
<h1>404</h1>
<p>ページが見つかりません。バグかもしれません。</p>
<p><a href="/">トップへ戻る</a></p>
</div>
</body>
</html>
