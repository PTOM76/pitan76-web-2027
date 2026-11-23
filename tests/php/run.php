<?php
/**
 * src/ の関数の単体テスト。依存はなく、php tests/php/run.php で実行する。失敗があれば終了コード 1 で終わる。
 * テストのデータはすべて架空の値で書く。
 */
declare(strict_types=1);

const ROOT = __DIR__ . '/../..';
const TEMPLATE_DIR = ROOT . '/templates';
foreach (['util', 'image', 'works', 'icons', 'validate', 'render', 'terminal'] as $lib) require ROOT . "/src/$lib.php";

$failures = 0;
$count = 0;

/** $actual と $expected が等しいかを確かめる。 */
function same(mixed $actual, mixed $expected, string $label): void {
    global $failures, $count;
    $count++;
    if ($actual === $expected) return;
    $failures++;
    fwrite(STDERR, "FAIL: $label\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n");
}

/** $fn が例外を投げるかを確かめる。 */
function throws(Closure $fn, string $label): void {
    global $failures, $count;
    $count++;
    try {
        $fn();
    } catch (Throwable) {
        return;
    }
    $failures++;
    fwrite(STDERR, "FAIL: $label（例外が投げられませんでした）\n");
}

// ---------- util.php ----------
same(h('<a href="x">&</a>'), '&lt;a href=&quot;x&quot;&gt;&amp;&lt;/a&gt;', 'h: HTML をエスケープする');
same(h(null), '', 'h: null は空文字');
same(str_contains(js_json(['s' => '</script>']), '</'), false, 'js_json: </script> で閉じられない');

same(ymd('2026/08/24'), '2026-08-24', 'ymd: スラッシュ区切り');
same(ymd('20260926'), '2026-09-26', 'ymd: 区切りなし');
same(ymd('2026-1-5 12:00'), '2026-01-05', 'ymd: 1 桁の月日と時刻');
same(ymd('不明'), '', 'ymd: 読めない日付は空文字');
same(ymd(null), '', 'ymd: null は空文字');

same(plain("<p>一行目<br>二行目</p>\n\n<b>&amp;</b>"), '一行目 二行目 &', 'plain: タグを除き、空白をまとめる');
same(slug('my mod/v2'), 'my_mod_v2', 'slug: 使えない文字を _ にする');

same(fill_placeholders('作品は {n} 件', ['n' => '12']), '作品は <b class="num">12</b> 件', 'fill_placeholders: 値を埋め込む');
same(fill_placeholders('<{n}>', ['n' => '&']), '&lt;<b class="num">&amp;</b>&gt;', 'fill_placeholders: 文章も値もエスケープする');
throws(static fn() => fill_placeholders('{none}', []), 'fill_placeholders: 存在しない値は例外');

$tmp = sys_get_temp_dir() . '/pitan-test-' . getmypid();
ensure_dir($tmp);
touch("$tmp/old", time() - 100);
touch("$tmp/new", time());
same(is_stale("$tmp/missing", "$tmp/new"), true, 'is_stale: キャッシュがなければ true');
same(is_stale("$tmp/old", "$tmp/new"), true, 'is_stale: 元より古ければ true');
same(is_stale("$tmp/new", "$tmp/old"), false, 'is_stale: 元より新しければ false');
rrmdir($tmp);
same(is_dir($tmp), false, 'rrmdir: フォルダごと消す');

// ---------- validate.php ----------
$schema = ['name' => Field::Text, 'tags' => Field::List, 'rows' => ['id' => Field::Text]];
same(validate_data(['name' => 'a', 'tags' => [], 'rows' => [['id' => 'x']]], $schema, 'f'), [], 'validate_data: 正しいデータはエラーなし');
same(validate_data(['tags' => 'x', 'rows' => [['id' => 1], 'y']], $schema, 'f'), [
    'f.name がありません',
    'f.tags は配列にしてください',
    'f.rows[0].id は文字列にしてください',
    'f.rows[1] の形が違います',
], 'validate_data: 足りない項目と型の違いを全部返す');

// ---------- works.php ----------
same(normalize_works([
    ['id' => 'a', 'createAt' => '2024/01/01', 'updateAt' => ''],
    ['id' => 'b', 'createAt' => '2023/01/01', 'updateAt' => '2025/06/01'],
])[0]['id'], 'b', 'normalize_works: 更新日の新しい順に並べる');

$item = build_items(
    [['id' => 'demo mod', 'name' => 'Demo', 'createAt' => '2025-01-01', 'updateAt' => '2025-02-01', 'category' => 'mcmod',
      'site' => ['github' => 'https://example.com/repo', 'bad' => 'javascript:alert(1)']]],
    [], ['demo mod' => 'demo.png'], [], ['categories' => ['mcmod' => 'Minecraft'], 'linkLabels' => []], ['other', 'demo mod'],
)[0];
same($item['page'], 'works/demo_mod.html', 'build_items: ページのパスは slug にする');
same($item['cat'], 'Minecraft', 'build_items: カテゴリ名を変換する');
same($item['updated'], true, 'build_items: 公開日と更新日が違えば更新扱い');
same($item['featured'], 1, 'build_items: 代表作の順番（0 始まり）');
same($item['icon'], 'assets/img/works/demo.png', 'build_items: アイコンのパス');
same(array_column($item['links'], 'url'), ['https://example.com/repo'], 'build_items: http 以外のリンクは除く');

same(build_items([['id' => 'x', 'name' => 'X', 'createAt' => '', 'updateAt' => '']], [], [], [], ['categories' => [], 'linkLabels' => []], ['x'])[0]['featured'], 0,
    'build_items: 先頭の代表作も 0 として扱う（null にしない）');

$log = recent_log([
    ['date' => '2025-03-01', 'updated' => true, 'name' => 'A'],
    ['date' => '2025-03-01', 'updated' => true, 'name' => 'B'],
    ['date' => '2025-02-01', 'updated' => false, 'name' => 'C'],
    ['date' => '', 'updated' => false, 'name' => 'D'],
    ['date' => '2025-01-01', 'updated' => false, 'name' => 'E'],
], 2);
same(array_map(static fn($e) => count($e['works']), $log), [2, 1], 'recent_log: 同じ日の同じ種類をまとめ、行数を制限する');

same(resolve_url('https://example.com/a/b.html', 'https://cdn.example.com/x.png'), 'https://cdn.example.com/x.png', 'resolve_url: 絶対 URL はそのまま');
same(resolve_url('https://example.com/a/b.html', '//cdn.example.com/x.png'), 'https://cdn.example.com/x.png', 'resolve_url: スキーム省略');
same(resolve_url('https://example.com/a/b.html', '/icon.png'), 'https://example.com/icon.png', 'resolve_url: ルートからのパス');
same(resolve_url('https://example.com/a/b.html', 'icon.png'), 'https://example.com/a/icon.png', 'resolve_url: 相対パス');
same(resolve_url('https://example.com', 'icon.png'), 'https://example.com/icon.png', 'resolve_url: パスのない URL');
same(url_origin('https://example.com:8080/a?b'), 'https://example.com:8080', 'url_origin: ポートを含める');

same(html_tags('<link href="/a.png" sizes="32x32" rel="icon"><link rel=manifest href=\'/m.json\'>', 'link'), [
    ['href' => '/a.png', 'sizes' => '32x32', 'rel' => 'icon'],
    ['rel' => 'manifest', 'href' => '/m.json'],
], 'html_tags: 属性の順序や引用符に関係なく読む');
same(html_tags('<meta content="a&amp;b" property="og:image">', 'meta')[0]['content'], 'a&b', 'html_tags: 実体参照を戻す');

$png = (static function (): string {
    $img = imagecreatetruecolor(2, 2);
    ob_start();
    imagepng($img);
    return (string)ob_get_clean();
})();
same(decode_icon($png) instanceof GdImage, true, 'decode_icon: PNG を読む');
same(decode_icon("\0\0\1\0" . str_repeat("\0", 18) . $png) instanceof GdImage, true, 'decode_icon: PNG 入りの ICO を読む');
same(decode_icon('<html>'), null, 'decode_icon: 画像でなければ null');
same(decode_icon(null), null, 'decode_icon: null は null');

// ---------- icons.php ----------
$src = ['devicon' => 'https://devicon.test', 'simpleIcons' => 'https://si.test'];
same(IconRef::parse('java/java-original', $src)->file, 'java-original.svg', 'IconRef: Devicon');
same(IconRef::parse('si:rust:000000', $src)->file, 'si-rust.svg', 'IconRef: Simple Icons');
same(IconRef::parse('https://example.com/logo.png', $src)->file, 'url-logo.png', 'IconRef: URL');

// ---------- render.php ----------
$view = new View(['site' => ['title' => 'T']], ['assets/x.css' => 'abc']);
same($view->at('../')->prefix, '../', 'View::at: 前置きだけを変える');
same($view->at('../')->asset('assets/x.css'), '../assets/x.css?v=abc', 'View::asset: 前置きとハッシュを付ける');
same($view->prefix, '', 'View::at: 元の View は変えない');
throws(static fn() => $view->render('no-such-template'), 'View::render: ないテンプレートは例外');
throws(static fn() => View::current(), 'View::current: テンプレートの外では例外');

// ---------- terminal.php ----------
$term = terminal_text(['author' => '<A>', 'lead' => 'L', 'skills' => [['name' => 'G', 'items' => ['x']]]], [['name' => 'P', 'summary' => 'S']]);
same($term['whoami'], '&lt;A&gt; — L', 'terminal_text: HTML をエスケープする');
same($term['ls'], '[1] P — S', 'terminal_text: ls の番号は 1 から');

echo $failures ? "\n$failures / $count failed\n" : "$count passed\n";
exit($failures ? 1 : 0);
