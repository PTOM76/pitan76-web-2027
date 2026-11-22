<?php /** トップページの重ねて出す部品（配達キャラ、図鑑、紹介カード、掘り当てたカード、ターミナルなど）。中身は main.js が入れる。 */ ?>
<div class="courier" id="courier">
  <button type="button" class="courier-btn" id="courier-btn" aria-label="キャラクターを見る"><img src="assets/img/ftp.png" alt="" draggable="false"></button>
  <div class="courier-log"><span id="courier-cmd"></span><i><b id="courier-bar"></b></i></div>
</div>

<div class="dex" id="dex" hidden>
  <div class="dex-win inv sk" role="dialog" aria-labelledby="dex-title">
    <button type="button" class="loot-close" id="dex-close" aria-label="閉じる">×</button>
    <h2 class="dex-title" id="dex-title">作品図鑑</h2>
    <p class="dex-count" id="dex-count"></p>
    <ul class="dex-grid" id="dex-grid"></ul>
    <p class="dex-hint">背景のブロックを掘って作品を見つけると、ここに登録されます。記録はこのブラウザに保存されます。</p>
  </div>
</div>

<div class="chara" id="chara" hidden>
  <div class="chara-card sk" role="dialog" aria-labelledby="chara-name">
    <button type="button" class="loot-close" id="chara-close" aria-label="閉じる">×</button>
    <img id="chara-img" alt="" draggable="false">
    <div class="chara-text">
      <small id="chara-proto"></small>
      <b id="chara-name"></b>
      <p id="chara-desc"></p>
    </div>
  </div>
</div>

<div class="loot" id="loot" hidden>
  <div class="loot-card sk" id="loot-card" role="dialog" aria-labelledby="loot-name">
    <button type="button" class="loot-close" id="loot-close" aria-label="閉じる">×</button>
    <img id="loot-img" alt="">
    <span class="loot-get">掘り当てた！</span>
    <b id="loot-name"></b>
    <small id="loot-meta"></small>
    <p id="loot-desc"></p>
    <div class="loot-note" id="loot-note" hidden><span>つくった理由</span><p></p></div>
    <dl class="loot-spec" id="loot-spec"></dl>
    <p class="loot-tags" id="loot-tags"></p>
    <p class="loot-links" id="loot-links"></p>
  </div>
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
