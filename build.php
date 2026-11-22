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

/**
 * サイトのアイコンをブロック風のドット絵にする。
 * 丸い元画像の内接正方形を 14x14 に縮めて色数を減らし、焦げ茶の枠と立体の縁を付けて 16x16 にする。
 * それを整数倍に拡大して出力する。
 */
function block_icon(string $src, string $dst, int $size): void {
    $img = imagecreatefrompng($src);
    $w = imagesx($img);
    $side = (int)floor($w / M_SQRT2);
    $off = intdiv($w - $side, 2);
    $px = imagecreatetruecolor(16, 16);
    imagecopyresampled($px, $img, 1, 1, $off, $off, 14, 14, $side, $side);
    $ink = [43, 23, 16];
    for ($y = 0; $y < 16; $y++) {
        for ($x = 0; $x < 16; $x++) {
            if ($x === 0 || $y === 0 || $x === 15 || $y === 15) {
                [$r, $g, $b] = $ink;
            } else {
                $c = imagecolorat($px, $x, $y);
                // 6 段階に減色
                [$r, $g, $b] = array_map(static fn($v) => (int)(round($v / 51) * 51), [($c >> 16) & 255, ($c >> 8) & 255, $c & 255]);
                // 上と左の縁は明るく、下と右の縁は暗くして立体に見せる
                $k = ($x === 1 || $y === 1) ? 1.3 : (($x === 14 || $y === 14) ? 0.7 : 1.0);
                [$r, $g, $b] = array_map(static fn($v) => max(0, min(255, (int)($v * $k + ($k > 1 ? 18 : 0)))), [$r, $g, $b]);
            }
            imagesetpixel($px, $x, $y, imagecolorallocate($px, $r, $g, $b));
        }
    }
    $out = imagecreatetruecolor($size, $size);
    imagecopyresized($out, $px, 0, 0, 0, 0, $size, $size, 16, 16);   // 最近傍で拡大
    imagepng($out, $dst, 9);
}

/** 技術スタックの名前 => Simple Icons の [slug, title]。null はロゴなし（文字のマークで表示）。 */
const SIMPLE_ICONS = 'https://cdn.jsdelivr.net/npm/simple-icons@13.21.0';
const SKILL_ICONS = [
    'Java' => ['openjdk', 'OpenJDK'], 'PHP' => ['php', 'PHP'], 'JavaScript' => ['javascript', 'JavaScript'],
    'TypeScript' => ['typescript', 'TypeScript'], 'C / C++' => ['cplusplus', 'C++'], 'Python' => ['python', 'Python'],
    'React' => ['react', 'React'], 'Next.js' => ['nextdotjs', 'Next.js'], 'Node.js' => ['nodedotjs', 'Node.js'],
    'HTML / CSS' => ['html5', 'HTML5'], 'REST API' => ['openapiinitiative', 'OpenAPI Initiative'], 'PWA' => ['pwa', 'PWA'],
    'Cloudflare' => ['cloudflare', 'Cloudflare'], 'Workers' => ['cloudflareworkers', 'Cloudflare Workers'],
    'Docker' => ['docker', 'Docker'], 'Linux' => ['linux', 'Linux'], 'GitHub Actions' => ['githubactions', 'GitHub Actions'],
    'Rust / WASM' => ['rust', 'Rust'],
];

/**
 * 技術スタックのロゴ（SVG のパスとブランドカラー）を取得する。キャッシュがあればそれを使う。
 * 返り値は 名前 => ['path' => ..., 'hex' => ...]。取得できなかったものは含まない。
 */
function skill_icons(array $skills, string $cacheDir): array {
    $dir = "$cacheDir/icons";
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    $dataFile = "$dir/simple-icons.json";
    if (!is_file($dataFile) && ($raw = http_get(SIMPLE_ICONS . '/_data/simple-icons.json'))) file_put_contents($dataFile, $raw);
    $hexByTitle = [];
    if (is_file($dataFile)) {
        $data = json_data($dataFile);
        foreach ($data['icons'] ?? $data as $i) $hexByTitle[$i['title']] = $i['hex'];
    }
    $out = [];
    foreach ($skills as $g) {
        foreach ($g['items'] as $name) {
            if (!isset(SKILL_ICONS[$name])) continue;
            [$slug, $title] = SKILL_ICONS[$name];
            $file = "$dir/$slug.svg";
            if (!is_file($file) && ($raw = http_get(SIMPLE_ICONS . "/icons/$slug.svg"))) file_put_contents($file, $raw);
            if (!is_file($file) || !preg_match('/<path d="([^"]+)"/', file_get_contents($file), $m)) {
                fwrite(STDERR, "warning: icon skipped: $slug\n");
                continue;
            }
            $out[$name] = ['path' => $m[1], 'hex' => $hexByTitle[$title] ?? '2b1710'];
        }
    }
    return $out;
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
$icons = skill_icons($site['skills'], $cacheDir);

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
$images = ['pitan' => 640, 'ftp' => 480, 'http' => 480];
foreach ($images as $name => $width) {
    $src = "$root/assets/img/$name.png";
    $cached = "$cacheDir/$name.png";
    if (!is_file($cached) || filemtime($cached) < filemtime($src)) resize_png($src, $cached, $width);
    copy($cached, "$out/assets/img/$name.png");
}
foreach (['favicon' => 64, 'favicon-32' => 32, 'apple-touch-icon' => 192] as $name => $size) {
    $src = "$root/assets/img/favicon-src.png";
    $cached = "$cacheDir/$name.png";
    if (!is_file($cached) || filemtime($cached) < filemtime($src) || filemtime($cached) < filemtime(__FILE__)) block_icon($src, $cached, $size);
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
