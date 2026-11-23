<?php
/**
 * 静的サイトを dist/ に生成する。
 *   php build.php          通常のビルド（dist/ を作り直す）
 *   PITAN_DEV=1            開発用。作品一覧はキャッシュを優先し、dist/ は消さずに上書きする
 *   PITAN_BASE=/path/      公開先のパス（404 ページのリンクに使う。既定は /）
 *   PITAN_URL=https://...  公開先の URL（sitemap.xml と OGP に使う。未指定なら出力しない）
 */
declare(strict_types=1);

const ROOT = __DIR__;
const TEMPLATE_DIR = ROOT . '/templates';
foreach (['util', 'image', 'works', 'icons', 'validate', 'render', 'terminal'] as $lib) require ROOT . "/src/$lib.php";

$out = ROOT . '/dist';
$cache = ROOT . '/.cache';
$dev = (bool)getenv('PITAN_DEV');
$base = rtrim(getenv('PITAN_BASE') ?: '/', '/') . '/';
$url = rtrim(getenv('PITAN_URL') ?: '', '/');

// ---------- データの読み込みと検証 ----------
$config = json_data(ROOT . '/data/config.json');
$site = json_data(ROOT . '/data/site.json');
$projects = json_data(ROOT . '/data/projects.json');
$characters = json_data(ROOT . '/data/characters.json');
$notes = is_file(ROOT . '/data/notes.json') ? json_data(ROOT . '/data/notes.json') : [];

$errors = array_merge(
    validate_data($site, SITE_SCHEMA, 'site.json'),
    validate_data(['projects' => $projects], ['projects' => PROJECT_SCHEMA], 'projects.json'),
    ...array_map(static fn($k, $c) => validate_data($c, CHARACTER_SCHEMA, "characters.json.$k"), array_keys($characters), $characters),
);
if (!isset($site['links']['github'])) $errors[] = 'site.json.links.github がありません';
foreach ($characters as $k => $_) {
    if (!is_file(ROOT . "/assets/img/$k.png")) $errors[] = "characters.json.$k の画像 assets/img/$k.png がありません";
}
if ($errors) {
    fwrite(STDERR, "data error:\n  " . implode("\n  ", $errors) . "\n");
    exit(1);
}

// ---------- 作品・画像・ロゴ ----------
$works = normalize_works(fetch_works($config['worksUrl'], "$cache/works.json", $dev));
$thumbs = work_thumbs($works, "$cache/works");
$icons = skill_icons($site['skills'], $config, "$cache/icons");
$workIcons = work_icons($works, ROOT . '/assets/img/works');
$items = build_items($works, $thumbs, $workIcons, $notes, $config, array_column($projects, 'work'));
$byId = array_column($items, null, 'id');

$projects = array_map(static function (array $p) use ($byId): array {
    if (!isset($byId[$p['work']])) warn("projects.json: work \"{$p['work']}\" が works-info にありません");
    return $p + ['thumb' => $byId[$p['work']]['thumb'] ?? null];
}, $projects);
foreach (array_keys($notes) as $id) {
    if (!isset($byId[$id])) warn("notes.json: \"$id\" が works-info にありません");
}

// ---------- 出力 ----------
if (!$dev) rrmdir($out);   // 開発時は監視中のサーバーと競合しないよう上書きのみ

$versions = [];
require_once ROOT . '/scripts/php/minify.php';
foreach (['style.css', 'main.js', 'scene.js'] as $f) {
    if ($dev) {
        copy_file(ROOT . "/assets/$f", "$out/assets/$f");   // 開発時はデバッグしやすいよう圧縮しない
    } else {
        ensure_dir("$out/assets");
        $src = file_get_contents(ROOT . "/assets/$f");
        file_put_contents("$out/assets/$f", str_ends_with($f, '.css') ? Minify\css($src) : Minify\js($src));
    }
    $versions["assets/$f"] = substr(md5_file(ROOT . "/assets/$f"), 0, 8);
}
// 効果音は手元で生成してコミットしておく。ないときだけ生成する（ffmpeg が必要）
if (!is_file(ROOT . '/assets/sfx/sfx.json')) {
    require ROOT . '/scripts/php/sfx.php';
    Sfx\generate(ROOT . '/assets/sfx');
}
foreach (glob(ROOT . '/assets/sfx/*.{m4a,json}', GLOB_BRACE) as $f) {
    copy_file($f, "$out/assets/sfx/" . basename($f));
}
foreach ($config['images'] +array_fill_keys(array_keys($characters), 480) as $name => $width) {
    $src = ROOT . "/assets/img/$name.png";
    $png = cached("$cache/img/$name-$width.png", [$src], static fn($to) => resize_png($src, $to, $width));
    copy_file($png, "$out/assets/img/$name.png");
}
$faviconSrc = ROOT . '/assets/img/favicon-src.png';
foreach ($config['favicons'] as $name => $size) {
    $png = cached("$cache/img/$name.png", [$faviconSrc, ROOT . '/src/image.php'], static fn($to) => round_pixel_icon($faviconSrc, $to, $size));
    copy_file($png, "$out/assets/img/$name.png");
}
// OGP 画像。文字は DotGothic16 で描く（取得できなければ文字なし）
$font = "$cache/fonts/DotGothic16.ttf";
if (!is_file($font) && ($raw = http_get($config['ogFont'])) !== null) write_file($font, $raw);
$og = cached("$cache/img/og.png", [ROOT . '/assets/img/pitan.png', ROOT . '/data/site.json', ROOT . '/src/image.php'],
    static fn($to) => og_image($to, ROOT . '/assets/img/pitan.png', is_file($font) ? $font : null, 'ぴたん', $site['title'], $site['lead']));
copy_file($og, "$out/assets/img/og.png");
copy_file("$cache/img/favicon-32.png", "$out/favicon.ico");   // /favicon.ico を直接読むブラウザ向け（PNG のまま）
foreach (array_unique(array_merge([], ...array_values($icons))) as $file) {
    copy_file("$cache/icons/$file", "$out/assets/icons/$file");
}
foreach ($workIcons as $file) {
    copy_file(ROOT . "/assets/img/works/$file", "$out/assets/img/works/$file");
}
foreach ($thumbs as $t) {
    foreach (glob("$cache/works/{$t['file']}{,-w,-[2-6]}.jpg", GLOB_BRACE) as $f) {
        copy_file($f, "$out/assets/works/" . basename($f));
    }
}

// ---------- ページ ----------
$ach = array_column($site['achievements'], 'value', 'id');
$view = new View(['site' => $site, 'config' => $config, 'url' => $url], $versions);
$pages = [
    'index.html' => $view->render('index', [
        'projects' => $projects,
        'items' => $items,
        'icons' => $icons,
        'characters' => $characters,
        'cats' => category_counts($items),
        'log' => recent_log($items, $config['chatlogLines']),
        'facts' => fill_placeholders($site['heroFacts'], array_map(static fn($v) => rtrim($v, '+'), $ach)),
    ]),
    // 404 はどの階層の URL でも表示されるため、パスは公開先から書く
    '404.html' => $view->at($base)->render('404'),
];
foreach ($items as $w) {
    $pages[$w['page']] = $view->at('../')->render('work', ['w' => $w]);
    // 掘り当てたときのカード。トップページに読み込んで表示するため、パスはトップ基準
    $pages[$w['card']] = $view->render('partials/work-card', ['w' => $w, 'loot' => true]);
}

foreach ($pages as $path => $html) write_file("$out/$path", $html);
write_file("$out/.nojekyll", '');
if ($url !== '') {
    $public = array_filter(array_keys($pages), static fn($path) => $path !== '404.html' && !str_ends_with($path, '.card.html'));
    $locs = array_map(static fn($path) => '  <url><loc>' . h("$url/" . ($path === 'index.html' ? '' : $path)) . '</loc></url>', $public);
    write_file("$out/sitemap.xml", "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n" . implode("\n", $locs) . "\n</urlset>\n");
    write_file("$out/robots.txt", "User-agent: *\nAllow: /\nSitemap: $url/sitemap.xml\n");
}

printf("Generated %d files: %d projects, %d works, %d thumbnails.\n", count($pages), count($projects), count($items), count($thumbs));
