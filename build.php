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
$items = build_items($works, $thumbs, $notes, $config, array_column($projects, 'work'));
$byId = array_column($items, null, 'id');

foreach ($projects as &$p) {
    if (!isset($byId[$p['work']])) warn("projects.json: work \"{$p['work']}\" が works-info にありません");
    $p['thumb'] = $byId[$p['work']]['thumb'] ?? null;
}
unset($p);
foreach (array_keys($notes) as $id) {
    if (!isset($byId[$id])) warn("notes.json: \"$id\" が works-info にありません");
}

// ---------- 出力 ----------
if (!$dev) rrmdir($out);   // 開発時は監視中のサーバーと競合しないよう上書きのみ

$versions = [];
foreach (['style.css', 'main.js', 'scene.js'] as $f) {
    copy_file(ROOT . "/assets/$f", "$out/assets/$f");
    $versions["assets/$f"] = substr(md5_file(ROOT . "/assets/$f"), 0, 8);
}
foreach ($config['images'] + array_fill_keys(array_keys($characters), 480) as $name => $width) {
    $src = ROOT . "/assets/img/$name.png";
    $cached = "$cache/img/$name-$width.png";
    if (is_stale($cached, $src)) {
        @mkdir(dirname($cached), 0777, true);
        resize_png($src, $cached, $width);
    }
    copy_file($cached, "$out/assets/img/$name.png");
}
foreach ($config['favicons'] as $name => $size) {
    $src = ROOT . '/assets/img/favicon-src.png';
    $cached = "$cache/img/$name.png";
    if (is_stale($cached, $src, ROOT . '/src/image.php')) {
        @mkdir(dirname($cached), 0777, true);
        round_pixel_icon($src, $cached, $size);
    }
    copy_file($cached, "$out/assets/img/$name.png");
}
copy_file("$cache/img/favicon-32.png", "$out/favicon.ico");   // /favicon.ico を直接読むブラウザ向け（PNG のまま）
foreach (array_unique(array_merge([], ...array_values($icons))) as $file) {
    copy_file("$cache/icons/$file", "$out/assets/icons/$file");
}
foreach ($thumbs as $t) {
    foreach (glob("$cache/works/{$t['file']}{,-w,-[2-6]}.jpg", GLOB_BRACE) as $f) {
        copy_file($f, "$out/assets/works/" . basename($f));
    }
}

// ---------- ページ ----------
$ach = array_column($site['achievements'], 'value', 'label');
View::$shared = [
    'site' => $site,
    'config' => $config,
    'versions' => $versions,
    'url' => $url,
    'p' => '',
];
$pages = [
    'index.html' => render('index', [
        'projects' => $projects,
        'items' => $items,
        'icons' => $icons,
        'characters' => $characters,
        'cats' => category_counts($items),
        'log' => recent_log($items, $config['chatlogLines']),
        'num' => static fn(string $label): string => rtrim($ach[$label] ?? '', '+'),
    ] + View::$shared),
];
View::$shared['p'] = '../';
foreach ($items as $w) {
    $pages[$w['page']] = render('work', ['w' => $w] + View::$shared);
}
// 掘り当てたときのカード。トップページに読み込んで表示するため、パスはトップ基準
View::$shared['p'] = '';
foreach ($items as $w) {
    $pages[$w['card']] = partial('work-card', ['w' => $w, 'loot' => true]);
}
View::$shared['p'] = $base;
$pages['404.html'] = render('404', View::$shared);

foreach ($pages as $path => $html) write_file("$out/$path", $html);
write_file("$out/.nojekyll", '');
if ($url !== '') {
    $locs = array_map(static fn($path) => "  <url><loc>" . h("$url/" . ($path === 'index.html' ? '' : $path)) . "</loc></url>", array_keys(array_diff_key($pages, ['404.html' => 1])));
    write_file("$out/sitemap.xml", "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n" . implode("\n", $locs) . "\n</urlset>\n");
    write_file("$out/robots.txt", "User-agent: *\nAllow: /\nSitemap: $url/sitemap.xml\n");
}

printf("Generated %d pages: %d projects, %d works, %d thumbnails.\n", count($pages), count($projects), count($items), count($thumbs));
