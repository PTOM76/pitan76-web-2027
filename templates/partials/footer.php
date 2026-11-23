<?php /** @var array $site @var bool $full トップページなら true（ターミナルの案内を出す） */ $full ??= false; ?>
<footer class="footer">
  <a href="<?= h($site['links']['github']) ?>" target="_blank" rel="noopener">GitHub</a>
  <p>© <?= date('Y') ?> <?= h($site['author']) ?> | Powered by PHP</p>
  <?php if ($full): ?><p class="footer-hint"><kbd>`</kbd> キーでターミナルが開きます</p><?php endif; ?>
</footer>
