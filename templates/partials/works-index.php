<?php
/**
 * 作品一覧。最初は $config['indexInitial'] 件だけ表示し、残りはボタンで開く。
 * @var array $items @var array $cats category_counts() の返り値 @var array $config
 */
if (!$items) return;
$initial = $config['indexInitial'];
$total = count($items);
?>
<section class="section" id="index">
  <h2 class="sec-title">作品一覧 <small><?= $total ?>件</small></h2>
  <div class="index-tools">
    <input type="search" id="index-q" placeholder="名前や説明で検索" aria-label="作品を検索">
    <div class="filters" role="group" aria-label="カテゴリ">
      <button type="button" class="filter sk is-on" data-cat="">すべて<small><?= $total ?></small></button>
      <?php foreach ($cats as $c => $n): ?>
        <button type="button" class="filter sk" data-cat="<?= h($c) ?>"><?= h($c) ?><small><?= $n ?></small></button>
      <?php endforeach; ?>
    </div>
  </div>
  <ol class="index sk" id="index-list">
    <?php foreach ($items as $n => $w): ?>
      <li<?php if ($n >= $initial): ?> class="is-more"<?php endif; ?> data-id="<?= h($w['id']) ?>" data-cat="<?= h($w['cat']) ?>" data-q="<?= h(mb_strtolower($w['name'] . ' ' . $w['desc'] . ' ' . implode(' ', $w['tags']))) ?>"<?php if ($w['thumb']): ?> data-thumb="<?= h($w['thumb']) ?>-w.jpg"<?php endif; ?>>
        <a href="<?= h($w['page']) ?>">
          <span class="ix-name"><?= h($w['name']) ?></span>
          <span class="ix-desc"><?= h($w['desc']) ?></span>
          <span class="ix-cat"><?= h($w['cat']) ?></span>
          <span class="ix-year"><?= h($w['year']) ?></span>
        </a>
      </li>
    <?php endforeach; ?>
  </ol>
  <?php if ($total > $initial): ?><button type="button" class="index-more sk" id="index-more">残りの<?= $total - $initial ?>件も表示</button><?php endif; ?>
  <div class="index-empty" id="index-empty" hidden>
    <img src="assets/img/http.png" alt="" width="160">
    <p class="sk"><b>HTTP/1.1 404 Not Found</b><br>そんな作品はないみたい。別の言葉で検索してください。</p>
  </div>
  <img class="index-preview" id="index-preview" alt="" aria-hidden="true">
</section>
