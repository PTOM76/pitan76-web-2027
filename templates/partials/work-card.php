<?php
/**
 * 作品カードの中身。作品の詳細ページと、ブロックを掘り当てたときのカード（$loot = true）で共通。
 * @var array $site @var string $p @var array $w build_items() の要素
 * @var bool $loot 掘り当てたときのカードなら true（見出しを h2 にし、「掘り当てた！」と詳細ページへのリンクを付ける）
 */
$loot ??= false;
$spec = work_spec($w);
$links = $w['links'] ?: [['label' => '開く', 'url' => $w['url'] ?: $site['links']['github']]];
$tag = $loot ? 'h2' : 'h1';
?>
<?php if ($w['thumb']): ?><img class="card-img" src="<?= h($p . $w['thumb']) ?>-w.jpg" alt="<?= h($w['name']) ?>の画面" width="640" height="360"<?= $loot ? ' loading="lazy"' : '' ?>><?php endif; ?>
<?php if ($loot): ?><span class="loot-get">掘り当てた！</span><?php endif; ?>
<<?= $tag ?> class="card-name"><?= h($w['name']) ?></<?= $tag ?>>
<small class="card-meta"><?= h(implode(' · ', array_filter([$w['cat'], $w['year']]))) ?></small>
<?php if ($w['desc']): ?><p><?= h($w['desc']) ?></p><?php endif; ?>
<?php if ($w['note']): ?><p class="loot-note"><?= h($w['note']) ?></p><?php endif; ?>
<?php if ($spec): ?>
<dl class="loot-spec">
  <?php foreach ($spec as $k => $v): ?><dt><?= h($k) ?></dt><dd><?= h($v) ?></dd><?php endforeach; ?>
</dl>
<?php endif; ?>
<?php if ($w['tags']): ?><p class="loot-tags"><?= h(implode(' ', array_map(static fn($t) => "#$t", $w['tags']))) ?></p><?php endif; ?>
<p class="loot-links">
  <?php foreach ($links as $l): ?><a href="<?= h($l['url']) ?>" target="_blank" rel="noopener"><?= h($l['label']) ?></a><?php endforeach; ?>
  <?php if ($loot): ?><a class="loot-page" href="<?= h($p . $w['page']) ?>">詳細ページ</a><?php endif; ?>
</p>
