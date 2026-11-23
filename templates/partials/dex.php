<?php
/**
 * 作品図鑑。マス目はすべて PHP で出力し、main.js は見つけた作品のマスに is-found を付けるだけにする。
 * 画像は data-src に置き、見つけたときに読み込む（未発見の作品の画像を先に読まないため）。
 * @var array $items
 */
?>
<div class="dex" id="dex" hidden>
  <div class="dex-win inv sk" role="dialog" aria-labelledby="dex-title">
    <button type="button" class="loot-close" id="dex-close" aria-label="閉じる"><?= icon('xmark') ?></button>
    <h2 class="dex-title" id="dex-title">作品図鑑</h2>
    <p class="dex-count"><span id="dex-total"><?= count($items) ?></span>件中<span id="dex-found">0</span>件発見</p>
    <ul class="dex-grid" id="dex-grid">
      <?php foreach ($items as $w): ?>
        <li><button type="button" class="slot dex-slot" data-id="<?= h($w['id']) ?>" data-name="<?= h($w['name']) ?>" aria-label="未発見" disabled>
          <span class="dex-q" aria-hidden="true">?</span>
          <?php if ($w['thumb']): ?>
            <img class="dex-img" data-src="<?= h($w['thumb']) ?>.jpg" alt="" draggable="false">
          <?php else: ?>
            <span class="slot-mark dex-img" aria-hidden="true"><?= h(substr(preg_replace('/[^A-Za-z0-9]/', '', $w['name']), 0, 2)) ?: icon('cube') ?></span>
          <?php endif; ?>
        </button></li>
      <?php endforeach; ?>
    </ul>
    <p class="dex-hint">背景のブロックを掘って作品を見つけると、ここに登録されます。記録はこのブラウザに保存されます。</p>
  </div>
</div>
