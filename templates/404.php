<?php /** @var array $site */ ?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>404 Not Found — <?= h($site['author']) ?></title>
<link rel="icon" href="/assets/img/favicon.png">
<link href="https://fonts.googleapis.com/css2?family=DotGothic16&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
:root { --paper: #ece8dd; --ink: #161514; --amber: #ffad0a; }
body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 16px; box-sizing: border-box; background: var(--paper); color: var(--ink); font: 14px/1.7 "JetBrains Mono", monospace;
  background-image: linear-gradient(#c9c3b3 1px, transparent 1px), linear-gradient(90deg, #c9c3b3 1px, transparent 1px); background-size: 32px 32px; }
.box { display: flex; align-items: flex-end; gap: 8px; flex-wrap: wrap; justify-content: center; }
img { width: 220px; transform: rotate(-6deg); filter: drop-shadow(3px 0 0 #fff) drop-shadow(-3px 0 0 #fff) drop-shadow(0 3px 0 #fff) drop-shadow(0 -3px 0 #fff) drop-shadow(5px 6px 0 rgba(22,21,20,.85)); }
.res { background: var(--paper); border: 2px solid var(--ink); box-shadow: 6px 6px 0 var(--ink); padding: 16px 20px; margin-bottom: 40px; }
h1 { font: 400 56px/1 "DotGothic16", monospace; margin: 0 0 8px; }
pre { margin: 0 0 12px; }
a { display: inline-block; background: var(--amber); border: 2px solid var(--ink); padding: 4px 12px; color: var(--ink); text-decoration: none; font-weight: 700; }
</style>
</head>
<body>
<div class="box">
  <img src="/assets/img/http.png" alt="HTTPちゃん">
  <div class="res">
    <h1>404</h1>
<pre>HTTP/1.1 404 Not Found
X-Served-By: HTTP-chan

そのページは見つかりませんでした。</pre>
    <a href="/">トップへ戻る</a>
  </div>
</div>
</body>
</html>
