<!doctype html>
<html lang="ja"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#10151d"><meta name="description" content="<?=h($site['description'])?>">
<title><?=h($site['title'])?></title><link rel="stylesheet" href="./assets/style.css">
</head><body>
<header class="topbar"><a class="logo" href="#top">P<span>.</span></a><nav><a href="#works">Works</a><a href="#history">History</a><a href="#achievements">Achievements</a><a href="#skills">Skills</a><a href="#qualifications">Qualifications</a><a href="#contact">Links</a></nav></header>
<main id="top">
<section class="hero wrap"><p class="eyebrow">PORTFOLIO</p><h1>Pitan</h1><p class="lead"><?=h($site['lead'])?></p><div class="hero-links"><a href="<?=h($site['links']['github'])?>" target="_blank" rel="noopener">GitHub ↗</a></div></section>
<section class="section wrap" id="works"><div class="section-head"><span>01</span><h2>Works</h2></div><div class="grid">
<?php foreach($projects as $i=>$p):?><article class="card"><div class="visual <?=h($p['visual'])?>"><span><?=h($p['visualLabel'])?></span><strong><?=h($p['symbol'])?></strong></div><div class="card-body"><div class="category"><?=h($p['category'])?></div><h3><?=h($p['name'])?></h3><p class="summary"><?=h($p['summary'])?></p><p class="detail"><?=h($p['detail'])?></p><div class="tags"><?php foreach($p['tags'] as $tag):?><span><?=h($tag)?></span><?php endforeach;?></div><div class="links"><?php if($p['url']):?><a href="<?=h($p['url'])?>" target="_blank" rel="noopener">Project ↗</a><?php endif;?><?php if($p['source']):?><a href="<?=h($p['source'])?>" target="_blank" rel="noopener">GitHub ↗</a><?php endif;?></div></div></article><?php endforeach;?>
</div></section>
<section class="section wrap" id="history"><div class="section-head"><span>02</span><h2>Development History</h2></div><div class="history-list"><?php foreach($site['history'] as $item):?><div class="history-item"><h3><?=h($item['period'])?></h3><p><?=h($item['items'])?></p></div><?php endforeach;?></div></section>
<section class="section wrap" id="achievements"><div class="section-head"><span>03</span><h2>Achievements</h2></div><div class="achievement-grid"><?php foreach($site['achievements'] as $item):?><div class="achievement"><strong><?=h($item['value'])?></strong><span><?=h($item['label'])?></span></div><?php endforeach;?></div><?php if($site['activities']):?><div class="activity-list"><?php foreach($site['activities'] as $activity):?><span><?=h($activity)?></span><?php endforeach;?></div><?php endif;?></section>
<section class="section wrap" id="skills"><div class="section-head"><span>04</span><h2>Skills</h2></div><?php foreach($site['skills'] as $g):?><div class="skill"><h3><?=h($g['name'])?></h3><div class="tags"><?php foreach($g['items'] as $v):?><span><?=h($v)?></span><?php endforeach;?></div></div><?php endforeach;?></section>
<section class="section wrap" id="qualifications"><div class="section-head"><span>05</span><h2>Qualifications</h2></div><div class="qualification-list"><?php foreach($site['qualifications'] as $q):?><div><?=h($q['name'])?></div><?php endforeach;?></div></section>
<section class="section wrap contact" id="contact"><div class="section-head"><span>06</span><h2>Links</h2></div><div class="contact-links"><a href="<?=h($site['links']['github'])?>" target="_blank" rel="noopener">GitHub ↗</a></div></section>
</main><footer class="footer wrap"><span>Pitan</span><small>© <?=date('Y')?> Pitan</small><a href="#top">TOP ↑</a></footer>
</body></html>