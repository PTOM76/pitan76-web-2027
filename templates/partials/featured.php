<?php /** 代表作。figure.work-slot の位置に、作品のブロックが飛んでくる。 @var array $projects */ ?>
<section class="section" id="works">
  <h2 class="sec-title">代表作</h2>
  <?php foreach ($projects as $i => $p): ?>
    <article class="work">
      <div class="work-text sk">
        <p class="work-cat"><?= h($p['category']) ?></p>
        <h3><a href="<?= h($p['url']) ?>" target="_blank" rel="noopener"><?= h($p['name']) ?></a></h3>
        <p class="work-summary"><?= h($p['summary']) ?></p>
        <p class="work-detail"><?= h($p['detail']) ?></p>
        <ul class="tags"><?php foreach ($p['tags'] as $t): ?><li><?= h($t) ?></li><?php endforeach; ?></ul>
        <p class="work-links">
          <a class="sk" href="<?= h($p['url']) ?>" target="_blank" rel="noopener">サイトを開く</a>
          <?php if (!empty($p['source'])): ?><a class="sk" href="<?= h($p['source']) ?>" target="_blank" rel="noopener">ソースコード</a><?php endif; ?>
        </p>
      </div>
      <figure class="work-slot" data-slot="<?= $i ?>">
        <?php if ($p['thumb']): ?><img src="<?= h($p['thumb']) ?>-w.jpg" alt="<?= h($p['name']) ?>の画面" loading="lazy" width="640" height="360"><?php endif; ?>
      </figure>
    </article>
  <?php endforeach; ?>
</section>
