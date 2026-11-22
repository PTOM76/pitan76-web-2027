<?php
declare(strict_types=1);

const WORKS_URL = 'https://raw.githubusercontent.com/PTOM76/works-info/main/list.json';
const CATEGORY_LABELS = [
    'mcmod' => 'Minecraft Mod', 'repomod' => 'Minecraft Mod', 'web' => 'Web', 'app' => 'App',
    'pukiwiki' => 'PukiWiki', 'pukiwikiskin' => 'PukiWiki', 'mcplugin' => 'MC Plugin',
    'mmdplugin' => 'MMD Plugin', 'chrome-extension' => 'Extension', 'misc' => 'Misc',
];

$root = __DIR__;
$out = "$root/dist";
$cacheDir = "$root/.cache";
$dev = (bool)getenv('PITAN_DEV');

function json_data(string $file): array {
    return json_decode(file_get_contents($file) ?: '[]', true, 512, JSON_THROW_ON_ERROR);
}

function h(mixed $v): string {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** 説明文の HTML を除去して 1 行のテキストにする。 */
/** works-info の日付（2026/08/24、20260926 など）を Y-m-d にそろえる。読めなければ空文字。 */
function ymd(?string $d): string {
    if (!$d || !preg_match('/^(\d{4})\D?(\d{1,2})\D?(\d{1,2})/', $d, $m)) return '';
    return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
}

function plain(string $html): string {
    $t = html_entity_decode(strip_tags(preg_replace('~<br\s*/?>~i', ' ', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace('/\s+/u', ' ', $t));
}

function http_get(string $url): ?string {
    $ctx = stream_context_create(['http' => ['timeout' => 15, 'header' => "User-Agent: pitan76-web-build\r\n"]]);
    $raw = @file_get_contents($url, false, $ctx);
    return $raw === false ? null : $raw;
}

/** works-info の作品一覧。開発時はキャッシュを優先し、取得に失敗した場合もキャッシュを利用する。 */
function fetch_works(string $cache, bool $dev): array {
    if ($dev && is_file($cache)) return json_data($cache);
    $raw = http_get(WORKS_URL);
    if ($raw === null) {
        fwrite(STDERR, "warning: works-info could not be fetched\n");
        return is_file($cache) ? json_data($cache) : [];
    }
    file_put_contents($cache, $raw);
    return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
}

/** 画像を中央で切り抜いて指定サイズに縮小する。 */
function cover(GdImage $src, int $w, int $h): GdImage {
    $sw = imagesx($src); $sh = imagesy($src);
    $scale = max($w / $sw, $h / $sh);
    $cw = (int)round($w / $scale); $ch = (int)round($h / $scale);
    $dst = imagecreatetruecolor($w, $h);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, intdiv($sw - $cw, 2), intdiv($sh - $ch, 2), $w, $h, $cw, $ch);
    return $dst;
}

/** 透過を保ったまま縮小する。 */
function resize_png(string $src, string $dst, int $width): void {
    $img = imagecreatefrompng($src);
    $h = (int)round(imagesy($img) * $width / imagesx($img));
    $res = imagecreatetruecolor($width, $h);
    imagealphablending($res, false);
    imagesavealpha($res, true);
    imagefill($res, 0, 0, imagecolorallocatealpha($res, 0, 0, 0, 127));
    imagecopyresampled($res, $img, 0, 0, 0, 0, $width, $h, imagesx($img), imagesy($img));
    imagepng($res, $dst, 9);
}

/**
 * 作品画像を取得し、ブロック用（正方形）とカード用（16:9）のサムネイルを作る。
 * 生成済みならキャッシュを使う。返り値は作品 ID => ファイル名。
 */
function work_thumbs(array $works, string $cacheDir): array {
    $dir = "$cacheDir/works";
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    $ok = [];
    foreach ($works as $w) {
        $url = $w['custom']['images'][0] ?? null;
        if (!$url) continue;
        $id = preg_replace('/[^a-z0-9_-]/i', '_', $w['id']);
        $sq = "$dir/$id.jpg"; $wide = "$dir/$id-w.jpg";
        if (!is_file($sq) || !is_file($wide)) {
            $raw = http_get($url);
            $img = $raw === null ? false : @imagecreatefromstring($raw);
            if (!$img) {
                fwrite(STDERR, "warning: image skipped: {$w['id']}\n");
                continue;
            }
            imagejpeg(cover($img, 256, 256), $sq, 82);
            imagejpeg(cover($img, 640, 360), $wide, 80);
        }
        $ok[$w['id']] = $id;
    }
    return $ok;
}

function rrmdir(string $dir): void {
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
}

if (!is_dir($cacheDir)) mkdir($cacheDir, 0777, true);
$site = json_data("$root/data/site.json");
$projects = json_data("$root/data/projects.json");
$works = fetch_works("$cacheDir/works.json", $dev);
$thumbs = work_thumbs($works, $cacheDir);

foreach ($works as &$w) {
    $w['createAt'] = ymd($w['createAt'] ?? null);
    $w['updateAt'] = ymd($w['updateAt'] ?? null);
}
unset($w);
usort($works, static fn($a, $b) => strcmp($b['updateAt'] ?: $b['createAt'], $a['updateAt'] ?: $a['createAt']));

// テンプレートと JS で使う作品データ
$featuredIds = array_column($projects, 'work');
$items = [];
foreach ($works as $w) {
    $f = array_search($w['id'], $featuredIds, true);
    $items[] = [
        'id' => $w['id'],
        'name' => $w['name'],
        'desc' => plain($w['description'] ?? ''),
        'cat' => CATEGORY_LABELS[$w['category'] ?? 'misc'] ?? 'Misc',
        'year' => substr($w['updateAt'] ?: $w['createAt'], 0, 4),
        'date' => $w['updateAt'] ?: $w['createAt'],
        'updated' => $w['updateAt'] !== '' && $w['createAt'] !== $w['updateAt'],
        'url' => $w['site']['modparks'] ?? $w['url'] ?? $w['site']['github'] ?? '',
        'tags' => $w['tag'] ?? [],
        'thumb' => isset($thumbs[$w['id']]) ? "assets/works/{$thumbs[$w['id']]}" : null,
        'featured' => $f === false ? null : $f,
    ];
}
foreach ($projects as &$p) {
    $p['thumb'] = isset($thumbs[$p['work']]) ? "assets/works/{$thumbs[$p['work']]}" : null;
}
unset($p);

// 出力
if (!$dev) rrmdir($out);   // 開発時は監視中のサーバーと競合しないよう上書きのみ
@mkdir("$out/assets/img", 0777, true);
@mkdir("$out/assets/works", 0777, true);
foreach (['style.css', 'main.js', 'scene.js'] as $f) copy("$root/assets/$f", "$out/assets/$f");
$images = ['pitan' => 640, 'ftp' => 480, 'http' => 480, 'favicon' => 64];
foreach ($images as $name => $width) {
    $src = "$root/assets/img/" . ($name === 'favicon' ? 'pitan' : $name) . '.png';
    $cached = "$cacheDir/$name.png";
    if (!is_file($cached) || filemtime($cached) < filemtime($src)) resize_png($src, $cached, $width);
    copy($cached, "$out/assets/img/$name.png");
}
foreach ($thumbs as $id) {
    copy("$cacheDir/works/$id.jpg", "$out/assets/works/$id.jpg");
    copy("$cacheDir/works/$id-w.jpg", "$out/assets/works/$id-w.jpg");
}

ob_start();
require "$root/templates/index.php";
file_put_contents("$out/index.html", ob_get_clean());
ob_start();
require "$root/templates/404.php";
file_put_contents("$out/404.html", ob_get_clean());
file_put_contents("$out/.nojekyll", '');

echo 'Generated ' . count($projects) . ' projects, ' . count($works) . ' works, ' . count($thumbs) . " thumbnails.\n";
