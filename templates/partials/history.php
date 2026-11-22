<?php /** これまで（経歴・資格・活動）。 @var array $site */ ?>
<section class="section" id="log">
  <h2 class="sec-title">これまで</h2>
  <div class="log">
    <ol class="history">
      <?php foreach ($site['history'] as $hst): ?>
        <li><b><?= h($hst['period']) ?></b><span><?= h($hst['items']) ?></span></li>
      <?php endforeach; ?>
    </ol>
    <div class="unlocks sk">
      <h3>資格</h3>
      <ul>
        <?php foreach ($site['qualifications'] as $q): ?><li><?= h($q['name']) ?></li><?php endforeach; ?>
      </ul>
      <h3>活動</h3>
      <ul>
        <?php foreach ($site['activities'] as $act): ?><li><?= h($act) ?></li><?php endforeach; ?>
      </ul>
    </div>
  </div>
</section>
