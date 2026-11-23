<?php
/**
 * @var array $site @var array $log recent_log() の返り値
 * @var string $facts site.json の heroFacts を展開した HTML
 */
?>
<section class="hero">
  <div class="hero-text">
    <h1 class="hero-title"><span class="ht-jp">ぴたん</span><span class="ht-en">PITAN</span></h1>
    <p class="hero-lead"><?= h($site['lead']) ?></p>
    <p class="hero-facts">
      <?= $facts ?>
    </p>
    <?php if ($log): ?>
    <ol class="chatlog" aria-label="最近の更新">
      <?php foreach ($log as $e): $first = $e['works'][0]; $more = count($e['works']) - 1; ?>
        <li><time datetime="<?= h($e['date']) ?>">[<?= h(date('n/j', strtotime($e['date']))) ?>]</time> <a href="<?= h($first['page']) ?>"><?= h($first['name']) ?></a><?php if ($more): ?> ほか<?= $more ?>件<?php endif; ?>を<?= $e['updated'] ? '更新' : '公開' ?>しました</li>
      <?php endforeach; ?>
    </ol>
    <?php endif; ?>
    <p class="hero-hint">背景のブロックは長押しで掘れます。色の粒が入ったブロックや絵の付いたブロックには、作品が埋まっています。<span class="hint-pc">Shift キーを押しながらドラッグすると、壊さずに回せます。</span></p>
  </div>
  <div class="hero-sticker">
    <button class="sticker" type="button" id="pitan" aria-label="ぴたんをなでる">
      <img src="assets/img/pitan.png" alt="ぴたん" width="640" height="640" draggable="false">
    </button>
    <p class="say sk" id="pitan-say" role="status"></p>
  </div>
</section>
