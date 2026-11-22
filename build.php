<?php
declare(strict_types=1);
$root=__DIR__; $out="$root/dist";
function json_data(string $file): array { return json_decode(file_get_contents($file) ?: '[]', true, 512, JSON_THROW_ON_ERROR); }
function h(?string $v): string { return htmlspecialchars($v ?? '', ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
$site=json_data("$root/data/site.json"); $projects=json_data("$root/data/projects.json");
if (!is_dir("$out/assets")) mkdir("$out/assets",0777,true);
copy("$root/assets/style.css","$out/assets/style.css"); copy("$root/assets/script.js","$out/assets/script.js");
ob_start(); require "$root/templates/index.php"; $html=ob_get_clean();
file_put_contents("$out/index.html",$html); file_put_contents("$out/.nojekyll","");
echo "Generated ".count($projects)." projects.\n";
