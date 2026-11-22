<?php /** 技術スタック（持ち物欄）。 @var array $site @var array $icons skill_icons() の返り値 @var array $config */ ?>
<section class="section" id="inventory">
  <h2 class="sec-title">技術スタック</h2>
  <div class="inventory">
    <?php foreach ($site['skills'] as $g): ?>
      <div class="inv sk">
        <h3 class="inv-label"><?= h($config['skillGroups'][$g['name']] ?? $g['name']) ?></h3>
        <ul>
          <?php foreach ($g['items'] as $it): $ic = $icons[$it] ?? null; ?>
            <li class="slot" tabindex="0">
              <?php if ($ic): ?>
                <span class="slot-icons<?= count($ic) > 1 ? ' is-pair' : '' ?>" aria-hidden="true"><?php foreach ($ic as $file): ?><img src="assets/icons/<?= h($file) ?>" alt="" width="34" height="34" loading="lazy" draggable="false"<?= str_ends_with($file, '.png') ? ' class="is-pixel"' : '' ?>><?php endforeach; ?></span>
              <?php else: ?>
                <span class="slot-mark" aria-hidden="true"><?= h(mb_substr(preg_replace('/[^A-Za-z0-9#]/', '', $it), 0, 2)) ?></span>
              <?php endif; ?>
              <span class="slot-name"><?= h($it) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endforeach; ?>
  </div>
</section>
