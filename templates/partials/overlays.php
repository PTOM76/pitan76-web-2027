<?php /** トップページの重ねて出す部品（配達キャラ、図鑑、紹介カード、掘り当てたカード、ターミナルなど）。 @var array $items */ ?>
<div class="courier" id="courier">
  <button type="button" class="courier-btn" id="courier-btn" aria-label="キャラクターを見る"><img src="assets/img/ftp.png" alt="" draggable="false"></button>
  <div class="courier-log"><span id="courier-cmd"></span><i><b id="courier-bar"></b></i></div>
</div>

<?= partial('dex', ['items' => $items]) ?>

<dialog class="chara" id="chara" aria-labelledby="chara-name">
  <div class="chara-card sk">
    <button type="button" class="loot-close" id="chara-close" aria-label="閉じる"><?= icon('xmark') ?></button>
    <img id="chara-img" alt="" draggable="false">
    <div class="chara-text">
      <small id="chara-proto"></small>
      <b id="chara-name"></b>
      <p id="chara-desc"></p>
    </div>
  </div>
</dialog>

<dialog class="loot" id="loot" aria-label="作品">
  <div class="loot-card sk" id="loot-card">
    <button type="button" class="loot-close" id="loot-close" aria-label="閉じる"><?= icon('xmark') ?></button>
    <!-- 中身は main.js が works/ID.card.html（PHP で生成）を読み込んで入れる -->
    <div class="loot-body" id="loot-body"></div>
  </div>
</dialog>

<dialog class="terminal" id="terminal" aria-label="ターミナル">
  <div class="term-win">
    <div class="term-bar"><p>pitan@bug-creator:~</p><button type="button" data-close-terminal aria-label="閉じる"><?= icon('xmark') ?></button></div>
    <div class="term-out" id="term-out"></div>
    <form class="term-in" id="term-form"><label for="term-input">$</label><input id="term-input" autocomplete="off" spellcheck="false"></form>
  </div>
</dialog>

<div class="block-tip" id="block-tip" aria-hidden="true"></div>
<div class="nudge" id="nudge" aria-hidden="true" hidden><?= icon('hand-pointer') ?></div>
<div class="toasts" id="toasts" aria-live="polite" popover="manual"></div>
<pre class="debug" id="debug" hidden></pre>
