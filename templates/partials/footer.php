<?php /** @var array $site @var bool $full トップページなら true（ターミナルの案内を出す） */ $full ??= false; ?>
<footer class="footer">
  <ul class="footer-links">
    <?php foreach ($site['links'] as $key => $href): [$label, $logo] = SOCIAL_LINKS[$key] ?? [ucfirst($key), 'link']; ?>
      <li><a href="<?= h($href) ?>" target="_blank" rel="noopener me" title="<?= h($label) ?>" aria-label="<?= h($label) ?>"><?= $logo === 'link' ? icon($logo) : icon($logo, 'brands') ?></a></li>
    <?php endforeach; ?>
  </ul>
  <p>© <?= date('Y') ?> <?= h($site['author']) ?> | Powered by PHP | <a href="https://github.com/PTOM76/pitan76-web-2027" target="_blank" rel="noopener">ソースコード</a></p>
  <?php if ($full): ?><p class="footer-hint"><kbd>`</kbd> キーでターミナルが開きます</p><?php endif; ?>
</footer>
