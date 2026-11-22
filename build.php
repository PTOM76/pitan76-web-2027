<?php
declare(strict_types=1);

$root = __DIR__;
$out = "$root/dist";
const WORKS_URL = 'https://raw.githubusercontent.com/PTOM76/works-info/main/list.json';

function json_data(string $file): array {
    return json_decode(file_get_contents($file) ?: '[]', true, 512, JSON_THROW_ON_ERROR);
}

function h(mixed $v): string {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** 外周から白背景を塗りつぶして透過 PNG を作る（内側の白は残す）。 */
function cutout(string $src, string $dst, int $maxWidth, float $sides = 0.6): void {
    $img = imagecreatefrompng($src);
    $w = imagesx($img);
    $hgt = imagesy($img);
    $isBg = static function (int $rgb): bool {
        $r = ($rgb >> 16) & 255; $g = ($rgb >> 8) & 255; $b = $rgb & 255;
        return min($r, $g, $b) > 232 && max($r, $g, $b) - min($r, $g, $b) < 24;
    };
    $mask = array_fill(0, $w * $hgt, 0);
    $stack = [];
    for ($x = 0; $x < $w; $x++) $stack[] = $x;                      // 上辺
    for ($y = 0; $y < (int)($hgt * $sides); $y++) {                  // 左右の上側（割合は $sides）
        $stack[] = $y * $w;
        $stack[] = $y * $w + $w - 1;
    }
    while ($stack) {
        $i = array_pop($stack);
        if ($mask[$i]) continue;
        $x = $i % $w; $y = intdiv($i, $w);
        if (!$isBg(imagecolorat($img, $x, $y))) continue;
        $mask[$i] = 1;
        if ($x > 0) $stack[] = $i - 1;
        if ($x < $w - 1) $stack[] = $i + 1;
        if ($y > 0) $stack[] = $i - $w;
        if ($y < $hgt - 1) $stack[] = $i + $w;
    }
    $res = imagecreatetruecolor($w, $hgt);
    imagealphablending($res, false);
    imagesavealpha($res, true);
    $clear = imagecolorallocatealpha($res, 255, 255, 255, 127);
    for ($y = 0; $y < $hgt; $y++) {
        for ($x = 0; $x < $w; $x++) {
            if ($mask[$y * $w + $x]) {
                imagesetpixel($res, $x, $y, $clear);
            } else {
                $c = imagecolorat($img, $x, $y);
                imagesetpixel($res, $x, $y, imagecolorallocatealpha($res, ($c >> 16) & 255, ($c >> 8) & 255, $c & 255, 0));
            }
        }
    }
    if ($w > $maxWidth) {
        $nh = (int)round($hgt * $maxWidth / $w);
        $scaled = imagecreatetruecolor($maxWidth, $nh);
        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);
        imagecopyresampled($scaled, $res, 0, 0, 0, 0, $maxWidth, $nh, $w, $hgt);
        $res = $scaled;
    }
    imagepng($res, $dst, 9);
}

function resized(string $src, string $dst, int $size): void {
    $img = imagecreatefrompng($src);
    $res = imagecreatetruecolor($size, $size);
    imagecopyresampled($res, $img, 0, 0, 0, 0, $size, $size, imagesx($img), imagesy($img));
    imagepng($res, $dst, 9);
}

/**
 * works-info の作品一覧を取得する。
 * 開発時（PITAN_DEV=1）はキャッシュを優先し、取得に失敗した場合もキャッシュを利用する。
 */
function fetch_works(string $cache): array {
    if (getenv('PITAN_DEV') && is_file($cache)) return json_data($cache);
    $ctx = stream_context_create(['http' => ['timeout' => 15, 'header' => "User-Agent: pitan76-web-build\r\n"]]);
    $raw = @file_get_contents(WORKS_URL, false, $ctx);
    if ($raw === false) {
        fwrite(STDERR, "warning: works-info could not be fetched\n");
        return is_file($cache) ? json_data($cache) : [];
    }
    file_put_contents($cache, $raw);
    return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
}

/** 変換結果をキャッシュし、元画像が更新されたときだけ再生成する。 */
function cached(string $src, string $name, callable $make): string {
    global $cacheDir;
    $dst = "$cacheDir/$name";
    if (!is_file($dst) || filemtime($dst) < filemtime($src)) $make($src, $dst);
    return $dst;
}

$site = json_data("$root/data/site.json");
$projects = json_data("$root/data/projects.json");
$cacheDir = "$root/.cache";
if (!is_dir($cacheDir)) mkdir($cacheDir, 0777, true);
$works = fetch_works("$cacheDir/works.json");

$categoryLabels = [
    'mcmod' => 'Minecraft Mod', 'web' => 'Web', 'app' => 'App', 'pukiwiki' => 'PukiWiki',
    'pukiwikiskin' => 'PukiWiki', 'mcplugin' => 'MC Plugin', 'mmdplugin' => 'MMD Plugin',
    'chrome-extension' => 'Extension', 'repomod' => 'Minecraft Mod', 'misc' => 'Misc',
];
$worksById = [];
foreach ($works as $w) $worksById[$w['id']] = $w;
foreach ($projects as &$p) {
    $p['image'] ??= $worksById[$p['work'] ?? '']['custom']['images'][0] ?? null;
}
unset($p);
usort($works, static fn($a, $b) => strcmp($b['updateAt'] ?? $b['createAt'] ?? '', $a['updateAt'] ?? $a['createAt'] ?? ''));

// 出力
if (is_dir($out)) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($out, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
}
mkdir("$out/assets/img", 0777, true);
foreach (['style.css', 'main.js', 'scene.js'] as $f) copy("$root/assets/$f", "$out/assets/$f");
$img = "$root/assets/img";
$images = [
    'pitan-cut.png' => cached("$img/pitan.png", 'pitan-cut.png', fn($s, $d) => cutout($s, $d, 640, 1.0)),
    'ftp.png' => cached("$img/ftp.png", 'ftp.png', fn($s, $d) => cutout($s, $d, 480, 1.0)),
    'http.png' => cached("$img/http.png", 'http.png', fn($s, $d) => cutout($s, $d, 480)),
    'pitan.png' => cached("$img/pitan.png", 'pitan.png', fn($s, $d) => resized($s, $d, 480)),
    'favicon.png' => cached("$img/pitan.png", 'favicon.png', fn($s, $d) => resized($s, $d, 64)),
];
foreach ($images as $name => $path) copy($path, "$out/assets/img/$name");

ob_start();
require "$root/templates/index.php";
file_put_contents("$out/index.html", ob_get_clean());
ob_start();
require "$root/templates/404.php";
file_put_contents("$out/404.html", ob_get_clean());
file_put_contents("$out/.nojekyll", '');

echo 'Generated ' . count($projects) . ' projects, ' . count($works) . " works.\n";
